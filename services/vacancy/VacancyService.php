<?php

namespace app\services\vacancy;

use app\models\Vacancy;
use app\models\VacancyDirection;
use app\services\media\MediaUrlResolver;
use yii\web\NotFoundHttpException;

class VacancyService
{
    private MediaUrlResolver $mediaUrls;

    public function __construct()
    {
        $this->mediaUrls = MediaUrlResolver::forPageContent();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildGroupsWithJobs(): array
    {
        $jobsByDirectionId = $this->loadVacanciesGrouped();
        $directions = VacancyDirection::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $result = [];
        foreach ($directions as $direction) {
            $group = $direction->toApiGroupPayload();
            $jobs = [];
            foreach ($jobsByDirectionId[(int)$direction->id] ?? [] as $vacancy) {
                $jobs[] = $this->buildListingJobPayload($vacancy);
            }
            $group['jobs'] = $jobs;
            $result[] = $group;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildAboutJobs(int $limit = 4): array
    {
        $groupOrder = array_map(
            'intval',
            VacancyDirection::find()
                ->select('id')
                ->where(['is_active' => true])
                ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                ->column()
        );

        $featuredGrouped = $this->loadVacanciesGrouped(['show_on_about' => true]);
        $picked = VacancyAboutJobSelector::select($featuredGrouped, $groupOrder, $limit);

        if (count($picked) < $limit) {
            $allGrouped = $this->loadVacanciesGrouped();
            $pickedIds = array_map(static fn (Vacancy $vacancy): int => (int)$vacancy->id, $picked);
            $extra = VacancyAboutJobSelector::select(
                $allGrouped,
                $groupOrder,
                $limit - count($picked),
                $pickedIds
            );
            $picked = array_merge($picked, $extra);
        }

        $result = [];
        foreach ($picked as $vacancy) {
            $result[] = $this->buildAboutJobPayload($vacancy);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBySlug(string $slug): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new NotFoundHttpException('Вакансия не найдена.');
        }

        $vacancy = Vacancy::find()
            ->where(['slug' => $slug])
            ->with(['direction'])
            ->one();

        if ($vacancy === null) {
            throw new NotFoundHttpException('Вакансия не найдена.');
        }

        return $this->buildDetailPayload($vacancy);
    }

    /**
     * @param array<string, mixed> $andWhere
     * @return array<int, array<int, Vacancy>>
     */
    private function loadVacanciesGrouped(array $andWhere = []): array
    {
        $vacancies = Vacancy::find()
            ->where($andWhere)
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_DESC])
            ->all();

        $grouped = [];
        foreach ($vacancies as $vacancy) {
            $directionId = (int)$vacancy->direction_id;
            if ($directionId <= 0) {
                continue;
            }

            $grouped[$directionId][] = $vacancy;
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildListingJobPayload(Vacancy $vacancy): array
    {
        $payload = array_filter([
            'slug' => trim($vacancy->slug),
            'title' => trim($vacancy->title),
            'description' => trim($vacancy->description),
            'salary' => trim($vacancy->salary),
        ], static fn (string $v): bool => $v !== '');

        $salaryMobile = trim($vacancy->salary_mobile);
        if ($salaryMobile !== '') {
            $payload['salaryMobile'] = $salaryMobile;
        }

        $meta = trim($vacancy->meta);
        if ($meta !== '') {
            $payload['meta'] = $meta;
        } else {
            foreach ([
                'department' => trim($vacancy->department),
                'schedule' => trim($vacancy->schedule),
                'location' => trim($vacancy->location),
            ] as $key => $value) {
                if ($value !== '') {
                    $payload[$key] = $value;
                }
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAboutJobPayload(Vacancy $vacancy): array
    {
        $category = trim($vacancy->category_label);
        if ($category === '') {
            $category = $vacancy->getDirectionLabel();
        }

        return array_filter([
            'category' => $category,
            'title' => trim($vacancy->title),
            'description' => trim($vacancy->description),
            'salary' => trim($vacancy->salary),
            'slug' => trim($vacancy->slug),
        ], static fn (string $v): bool => $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDetailPayload(Vacancy $vacancy): array
    {
        $payload = array_filter([
            'slug' => trim($vacancy->slug),
            'title' => trim($vacancy->title),
            'description' => trim($vacancy->description),
            'salary' => trim($vacancy->salary),
            'department' => trim($vacancy->department),
            'schedule' => trim($vacancy->schedule),
            'location' => trim($vacancy->location),
            'postedAt' => trim((string)$vacancy->posted_at),
            'requirements' => $vacancy->getRequirementsArray(),
            'conditions' => $vacancy->getConditionsArray(),
        ], static fn ($value): bool => $value !== '' && $value !== []);

        if ($vacancy->direction !== null) {
            $payload['directionId'] = $vacancy->direction->slug;
            $payload['directionTitle'] = trim($vacancy->direction->title);
        }

        $seoTitle = trim($vacancy->seo_title);
        $seoDescription = trim($vacancy->seo_description);
        if ($seoTitle !== '' || $seoDescription !== '') {
            $payload['seo'] = array_filter([
                'title' => $seoTitle,
                'description' => $seoDescription,
            ], static fn (string $v): bool => $v !== '');
        }

        return $this->mediaUrls->resolveTree($payload);
    }

    /**
     * @return array<int, array<int, Vacancy>>
     */
    public function vacanciesByDirectionForAdmin(): array
    {
        $vacancies = Vacancy::find()
            ->with(['direction'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_DESC])
            ->all();

        $grouped = [];
        foreach ($vacancies as $vacancy) {
            $directionId = (int)$vacancy->direction_id;
            $grouped[$directionId][] = $vacancy;
        }

        return $grouped;
    }
}
