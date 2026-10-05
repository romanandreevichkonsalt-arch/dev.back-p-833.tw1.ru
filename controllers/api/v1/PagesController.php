<?php

namespace app\controllers\api\v1;

use app\services\content\LegalDocumentsService;
use app\services\content\PageContentService;
use app\services\journal\JournalArticleService;
use app\services\vacancy\VacancyService;
use OpenApi\Annotations as OA;
use Yii;

class PagesController extends ApiController
{
    private PageContentService $pages;
    private LegalDocumentsService $legalDocuments;

    public function init(): void
    {
        parent::init();
        $this->pages = \Yii::$container->get(PageContentService::class);
        $this->legalDocuments = \Yii::$container->get(LegalDocumentsService::class);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = [
            'home', 'partners', 'designers', 'contacts', 'faq', 'journal', 'journal-article',
            'building', 'about', 'vacancies', 'vacancy-article', 'legal-documents', 'options',
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'home' => ['GET', 'OPTIONS'],
            'partners' => ['GET', 'OPTIONS'],
            'designers' => ['GET', 'OPTIONS'],
            'contacts' => ['GET', 'OPTIONS'],
            'faq' => ['GET', 'OPTIONS'],
            'journal' => ['GET', 'OPTIONS'],
            'journal-article' => ['GET', 'OPTIONS'],
            'building' => ['GET', 'OPTIONS'],
            'about' => ['GET', 'OPTIONS'],
            'vacancies' => ['GET', 'OPTIONS'],
            'vacancy-article' => ['GET', 'OPTIONS'],
            'legal-documents' => ['GET', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/home",
     *     tags={"Страницы"},
     *     summary="Главная страница",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки главной: hero, philosophy, collections (до 3 slides), products, partners, journal",
     *         @OA\JsonContent(ref="#/components/schemas/PageHomeResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionHome(): array
    {
        return $this->pages->getPage('home');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/partners",
     *     tags={"Страницы"},
     *     summary="Франшиза",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки страницы",
     *         @OA\JsonContent(ref="#/components/schemas/PageContentResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionPartners(): array
    {
        return $this->pages->getPage('partners');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/designers",
     *     tags={"Страницы"},
     *     summary="Дизайнерам",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки страницы",
     *         @OA\JsonContent(ref="#/components/schemas/PageContentResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionDesigners(): array
    {
        return $this->pages->getPage('designers');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/contacts",
     *     tags={"Страницы"},
     *     summary="Контакты",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки страницы: hero, info (PageContactsInfo — телефон, email, адрес, telegram, vkontakte, max), contact (privacyPolicyUrl, userAgreementUrl — PDF для форм), regions",
     *         @OA\JsonContent(ref="#/components/schemas/PageContentResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionContacts(): array
    {
        return $this->pages->getPage('contacts');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/legal-documents",
     *     tags={"Страницы"},
     *     summary="Политика конфиденциальности и пользовательское соглашение",
     *     description="Текстовые документы из CMS (страницы privacy-policy и user-agreement): slug, title, blocks[] как у статьи журнала. null, если страница не активна или блоки пустые. PDF для форм — в GET /pages/contacts → contact.privacyPolicyUrl и contact.userAgreementUrl.",
     *     @OA\Response(
     *         response=200,
     *         description="Оба документа",
     *         @OA\JsonContent(ref="#/components/schemas/LegalDocumentsResponse")
     *     )
     * )
     */
    public function actionLegalDocuments(): array
    {
        return $this->legalDocuments->getBoth();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/faq",
     *     tags={"Страницы"},
     *     summary="FAQ",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки страницы: hero, intro, categories с paragraphs (текст и ссылки)",
     *         @OA\JsonContent(ref="#/components/schemas/PageFaqResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionFaq(): array
    {
        return $this->pages->getPage('faq');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/journal",
     *     tags={"Страницы"},
     *     summary="Журнал (hero, вкладки, карточки статей из БД)",
     *     @OA\Response(
     *         response=200,
     *         description="Контент страницы журнала",
     *         @OA\JsonContent(ref="#/components/schemas/PageJournalResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionJournal(): array
    {
        return $this->pages->getPage('journal');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/journal/{slug}",
     *     tags={"Страницы"},
     *     summary="Статья журнала по slug (полный контент с blocks)",
     *     description="Алиас для GET /api/v1/journal/articles/{slug}",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="slug статьи",
     *         @OA\Schema(type="string", example="geometriya-komforta")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статья с блоками",
     *         @OA\JsonContent(ref="#/components/schemas/JournalArticleResponse")
     *     ),
     *     @OA\Response(response=404, description="Статья не найдена")
     * )
     */
    public function actionJournalArticle(string $slug): array
    {
        return Yii::$container->get(JournalArticleService::class)->getBySlug($slug, $this->resolveOptionalUser());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/building",
     *     tags={"Страницы"},
     *     summary="Производство",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки страницы",
     *         @OA\JsonContent(ref="#/components/schemas/PageContentResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionBuilding(): array
    {
        return $this->pages->getPage('building');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/about",
     *     tags={"Страницы"},
     *     summary="О нас",
     *     description="Блоки страницы и до 4 вакансий (по возможности из разных групп).",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки страницы и тизеры вакансий",
     *         @OA\JsonContent(ref="#/components/schemas/PageAboutResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionAbout(): array
    {
        return $this->pages->getPage('about');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/vacancies",
     *     tags={"Страницы"},
     *     summary="Вакансии (листинг)",
     *     description="Контент страницы: hero, values (вступление с slides[]), gallery. groups[] — направления vacancy_directions с активными вакансиями из БД.",
     *     @OA\Response(
     *         response=200,
     *         description="Блоки страницы и группы вакансий",
     *         @OA\JsonContent(ref="#/components/schemas/PageVacanciesResponse")
     *     ),
     *     @OA\Response(response=404, description="Страница не найдена")
     * )
     */
    public function actionVacancies(): array
    {
        return $this->pages->getPage('vacancies');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/vacancies/{slug}",
     *     tags={"Страницы"},
     *     summary="Карточка вакансии по slug",
     *     description="department, schedule, location, requirements[], conditions[], directionId (slug направления), directionTitle, postedAt.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", example="master-po-rabote-s-derevom")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Вакансия",
     *         @OA\JsonContent(ref="#/components/schemas/VacancyDetailResponse")
     *     ),
     *     @OA\Response(response=404, description="Вакансия не найдена")
     * )
     */
    public function actionVacancyArticle(string $slug): array
    {
        return Yii::$container->get(VacancyService::class)->getBySlug($slug);
    }
}
