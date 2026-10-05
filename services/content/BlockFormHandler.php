<?php

namespace app\services\content;

use app\modules\admin\helpers\BlockFormPostHelper;

class BlockFormHandler
{
    /**
     * @return array<string, mixed>
     */
    public function dataFromPost(string $type, array $post): array
    {
        return match ($type) {
            BlockTypeRegistry::TYPE_SEO => [
                'title' => trim((string)($post['seo_title'] ?? '')),
                'description' => trim((string)($post['seo_description'] ?? '')),
            ],
            BlockTypeRegistry::TYPE_HERO_SIMPLE => [
                'title' => trim((string)($post['hero_title'] ?? '')),
                'subtitle' => trim((string)($post['hero_subtitle'] ?? '')),
            ],
            BlockTypeRegistry::TYPE_HERO_MEDIA => HeroBannerPayload::fromPost($post),
            BlockTypeRegistry::TYPE_HERO_HOME => HeroBannerPayload::fromPost($post),
            BlockTypeRegistry::TYPE_PHILOSOPHY => [
                'text' => trim((string)($post['philosophy_text'] ?? '')),
            ],
            BlockTypeRegistry::TYPE_INTRO => $this->buildIntro($post),
            BlockTypeRegistry::TYPE_INTRO_TEXT => [
                'text' => trim((string)($post['intro_text'] ?? '')),
            ],
            BlockTypeRegistry::TYPE_CONTACT_CTA => $this->buildContactCta($post),
            BlockTypeRegistry::TYPE_INFO_CONTACTS => [
                'phone' => trim((string)($post['info_phone'] ?? '')),
                'phoneHref' => trim((string)($post['info_phone_href'] ?? '')),
                'email' => trim((string)($post['info_email'] ?? '')),
                'address' => trim((string)($post['info_address'] ?? '')),
                'telegram' => trim((string)($post['info_telegram'] ?? '')),
                'vkontakte' => trim((string)($post['info_vkontakte'] ?? '')),
                'max' => trim((string)($post['info_max'] ?? '')),
            ],
            BlockTypeRegistry::TYPE_COLLECTIONS => BlockFormBuilders::collectionsFromPost($post),
            BlockTypeRegistry::TYPE_HOME_PRODUCTS => BlockFormBuilders::homeProductsFromPost($post),
            BlockTypeRegistry::TYPE_HOME_PARTNERS => BlockFormBuilders::homePartnersFromPost($post),
            BlockTypeRegistry::TYPE_HOME_JOURNAL => BlockFormBuilders::journalCardsFromPost($post, 'items'),
            BlockTypeRegistry::TYPE_MISSION => BlockFormBuilders::missionFromPost($post),
            BlockTypeRegistry::TYPE_FORMATS_LIST => BlockFormBuilders::formatsFromPost($post),
            BlockTypeRegistry::TYPE_FORMATS_SECTION => BlockFormBuilders::formatsSectionFromPost($post),
            BlockTypeRegistry::TYPE_AUDIENCE_LIST => BlockFormBuilders::audienceFromPost($post),
            BlockTypeRegistry::TYPE_AUDIENCE_SECTION => BlockFormBuilders::audienceSectionFromPost($post),
            BlockTypeRegistry::TYPE_SALON_FORMATS_SECTION => BlockFormBuilders::salonFormatsSectionFromPost($post),
            BlockTypeRegistry::TYPE_GALLERY_SIMPLE => BlockFormBuilders::gallerySimpleFromPost($post),
            BlockTypeRegistry::TYPE_GALLERY_CAPTIONED => BlockFormBuilders::galleryCaptionedFromPost($post),
            BlockTypeRegistry::TYPE_GALLERY_STACK_SECTION => BlockFormBuilders::galleryStackSectionFromPost($post),
            BlockTypeRegistry::TYPE_TERMS => BlockFormBuilders::termsFromPost($post),
            BlockTypeRegistry::TYPE_PRESENTATION => BlockFormBuilders::presentationFromPost($post),
            BlockTypeRegistry::TYPE_PRESENTATION_SECTION => BlockFormBuilders::presentationSectionFromPost($post),
            BlockTypeRegistry::TYPE_MATERIALS_LIST => BlockFormBuilders::audienceFromPost($post),
            BlockTypeRegistry::TYPE_MATERIALS_SECTION => BlockFormBuilders::materialsSectionFromPost($post),
            BlockTypeRegistry::TYPE_SAMPLES => BlockFormBuilders::samplesFromPost($post),
            BlockTypeRegistry::TYPE_PHOTO_STACK => BlockFormBuilders::photoStackFromPost($post),
            BlockTypeRegistry::TYPE_REGIONS => BlockFormBuilders::regionsFromPost($post),
            BlockTypeRegistry::TYPE_FAQ_CATEGORIES => BlockFormBuilders::faqCategoriesFromPost($post),
            BlockTypeRegistry::TYPE_JOURNAL_CATEGORIES => BlockFormBuilders::journalCategoryTabsFromPost(
                $post,
                BlockFormPostHelper::rows($post['categories'] ?? null)
            ),
            BlockTypeRegistry::TYPE_COMFORT_LIST => BlockFormBuilders::comfortFromPost($post),
            BlockTypeRegistry::TYPE_STACK_LIST => BlockFormBuilders::stackSectionFromPost($post),
            BlockTypeRegistry::TYPE_STACK_SECTION => BlockFormBuilders::stackSectionFromPost($post),
            BlockTypeRegistry::TYPE_ETHICS => BlockFormBuilders::ethicsFromPost($post),
            BlockTypeRegistry::TYPE_TITLE_TEXT => BlockFormBuilders::titleTextFromPost($post),
            BlockTypeRegistry::TYPE_ABOUT_INTRO => BlockFormBuilders::aboutIntroFromPost($post),
            BlockTypeRegistry::TYPE_ABOUT_GALLERY => BlockFormBuilders::aboutGalleryFromPost($post),
            BlockTypeRegistry::TYPE_ABOUT_TIMELINE => BlockFormBuilders::aboutTimelineFromPost($post),
            BlockTypeRegistry::TYPE_VACANCIES_VALUES => BlockFormBuilders::vacanciesValuesFromPost($post),
            BlockTypeRegistry::TYPE_VACANCIES_GALLERY => BlockFormBuilders::vacanciesGalleryFromPost($post),
            BlockTypeRegistry::TYPE_LIBRARY_IMPLEMENTED_MODELS => BlockFormBuilders::libraryImplementedModelsFromPost($post),
            BlockTypeRegistry::TYPE_LIBRARY_YOUR_IDEA => BlockFormBuilders::libraryYourIdeaFromPost($post),
            BlockTypeRegistry::TYPE_LIBRARY_DOCUMENTS => BlockFormBuilders::libraryDocumentsFromPost($post),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function dataToForm(string $type, array $data): array
    {
        return match ($type) {
            BlockTypeRegistry::TYPE_SEO => [
                'seo_title' => $data['title'] ?? '',
                'seo_description' => $data['description'] ?? '',
            ],
            BlockTypeRegistry::TYPE_HERO_SIMPLE => [
                'hero_title' => $data['title'] ?? '',
                'hero_subtitle' => $data['subtitle'] ?? '',
            ],
            BlockTypeRegistry::TYPE_HERO_MEDIA => HeroBannerPayload::toForm($data),
            BlockTypeRegistry::TYPE_HERO_HOME => HeroBannerPayload::toForm($data),
            BlockTypeRegistry::TYPE_PHILOSOPHY => [
                'philosophy_text' => $data['text'] ?? '',
            ],
            BlockTypeRegistry::TYPE_INTRO => [
                'intro_lead' => $data['lead'] ?? '',
                'intro_text' => $data['text'] ?? '',
                'intro_image_src' => $data['image']['src'] ?? '',
                'intro_image_alt' => $data['image']['alt'] ?? '',
            ],
            BlockTypeRegistry::TYPE_INTRO_TEXT => [
                'intro_text' => $data['text'] ?? '',
            ],
            BlockTypeRegistry::TYPE_CONTACT_CTA => [
                'contact_title' => $data['title'] ?? '',
                'contact_subtitle' => $data['subtitle'] ?? '',
                'image_src' => $data['image']['src'] ?? '',
                'image_alt' => $data['image']['alt'] ?? '',
                'privacy_policy_url' => $data['privacyPolicyUrl'] ?? '',
                'user_agreement_url' => $data['userAgreementUrl'] ?? '',
            ],
            BlockTypeRegistry::TYPE_INFO_CONTACTS => [
                'info_phone' => $data['phone'] ?? '',
                'info_phone_href' => $data['phoneHref'] ?? '',
                'info_email' => $data['email'] ?? '',
                'info_address' => $data['address'] ?? '',
                'info_telegram' => $data['telegram'] ?? '',
                'info_vkontakte' => $data['vkontakte'] ?? '',
                'info_max' => $data['max'] ?? '',
            ],
            BlockTypeRegistry::TYPE_COLLECTIONS => BlockFormBuilders::collectionsToForm($data),
            BlockTypeRegistry::TYPE_HOME_PRODUCTS => BlockFormBuilders::homeProductsToForm($data),
            BlockTypeRegistry::TYPE_HOME_PARTNERS => BlockFormBuilders::homePartnersToForm($data),
            BlockTypeRegistry::TYPE_HOME_JOURNAL => BlockFormBuilders::journalCardsToForm($data, 'items'),
            BlockTypeRegistry::TYPE_MISSION => BlockFormBuilders::missionToForm($data),
            BlockTypeRegistry::TYPE_FORMATS_LIST => BlockFormBuilders::formatsToForm($data),
            BlockTypeRegistry::TYPE_FORMATS_SECTION => BlockFormBuilders::formatsSectionToForm($data),
            BlockTypeRegistry::TYPE_AUDIENCE_LIST => BlockFormBuilders::audienceToForm($data),
            BlockTypeRegistry::TYPE_AUDIENCE_SECTION => BlockFormBuilders::audienceSectionToForm($data),
            BlockTypeRegistry::TYPE_SALON_FORMATS_SECTION => BlockFormBuilders::salonFormatsSectionToForm($data),
            BlockTypeRegistry::TYPE_GALLERY_SIMPLE => BlockFormBuilders::gallerySimpleToForm($data),
            BlockTypeRegistry::TYPE_GALLERY_CAPTIONED => BlockFormBuilders::galleryCaptionedToForm($data),
            BlockTypeRegistry::TYPE_GALLERY_STACK_SECTION => BlockFormBuilders::galleryStackSectionToForm($data),
            BlockTypeRegistry::TYPE_TERMS => BlockFormBuilders::termsToForm($data),
            BlockTypeRegistry::TYPE_PRESENTATION => BlockFormBuilders::presentationToForm($data),
            BlockTypeRegistry::TYPE_PRESENTATION_SECTION => BlockFormBuilders::presentationSectionToForm($data),
            BlockTypeRegistry::TYPE_MATERIALS_LIST => BlockFormBuilders::audienceToForm($data),
            BlockTypeRegistry::TYPE_MATERIALS_SECTION => BlockFormBuilders::materialsSectionToForm($data),
            BlockTypeRegistry::TYPE_SAMPLES => BlockFormBuilders::samplesToForm($data),
            BlockTypeRegistry::TYPE_PHOTO_STACK => BlockFormBuilders::photoStackToForm($data),
            BlockTypeRegistry::TYPE_REGIONS => BlockFormBuilders::regionsToForm($data),
            BlockTypeRegistry::TYPE_FAQ_CATEGORIES => BlockFormBuilders::faqCategoriesToForm($data),
            BlockTypeRegistry::TYPE_JOURNAL_CATEGORIES => BlockFormBuilders::journalCategoriesTabsToForm($data),
            BlockTypeRegistry::TYPE_COMFORT_LIST => BlockFormBuilders::comfortToForm($data),
            BlockTypeRegistry::TYPE_STACK_LIST => BlockFormBuilders::stackSectionToForm($data),
            BlockTypeRegistry::TYPE_STACK_SECTION => BlockFormBuilders::stackSectionToForm($data),
            BlockTypeRegistry::TYPE_ETHICS => BlockFormBuilders::ethicsToForm($data),
            BlockTypeRegistry::TYPE_TITLE_TEXT => BlockFormBuilders::titleTextToForm($data),
            BlockTypeRegistry::TYPE_ABOUT_INTRO => BlockFormBuilders::aboutIntroToForm($data),
            BlockTypeRegistry::TYPE_ABOUT_GALLERY => BlockFormBuilders::aboutGalleryToForm($data),
            BlockTypeRegistry::TYPE_ABOUT_TIMELINE => BlockFormBuilders::aboutTimelineToForm($data),
            BlockTypeRegistry::TYPE_VACANCIES_VALUES => BlockFormBuilders::vacanciesValuesToForm($data),
            BlockTypeRegistry::TYPE_VACANCIES_GALLERY => BlockFormBuilders::vacanciesGalleryToForm($data),
            BlockTypeRegistry::TYPE_LIBRARY_IMPLEMENTED_MODELS => BlockFormBuilders::libraryImplementedModelsToForm($data),
            BlockTypeRegistry::TYPE_LIBRARY_YOUR_IDEA => BlockFormBuilders::libraryYourIdeaToForm($data),
            BlockTypeRegistry::TYPE_LIBRARY_DOCUMENTS => BlockFormBuilders::libraryDocumentsToForm($data),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function buildIntro(array $post): array
    {
        $result = array_filter([
            'lead' => trim((string)($post['intro_lead'] ?? '')),
            'text' => trim((string)($post['intro_text'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $src = trim((string)($post['intro_image_src'] ?? ''));
        if ($src !== '') {
            $result['image'] = [
                'src' => $src,
                'alt' => trim((string)($post['intro_image_alt'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function buildContactCta(array $post): array
    {
        $result = [
            'title' => trim((string)($post['contact_title'] ?? '')),
            'subtitle' => trim((string)($post['contact_subtitle'] ?? '')),
        ];

        $src = trim((string)($post['image_src'] ?? ''));
        if ($src !== '') {
            $result['image'] = [
                'src' => $src,
                'alt' => trim((string)($post['image_alt'] ?? '')) ?: $result['title'],
            ];
        }

        $policyUrl = trim((string)($post['privacy_policy_url'] ?? ''));
        if ($policyUrl !== '') {
            $result['privacyPolicyUrl'] = $policyUrl;
        }

        $agreementUrl = trim((string)($post['user_agreement_url'] ?? ''));
        if ($agreementUrl !== '') {
            $result['userAgreementUrl'] = $agreementUrl;
        }

        return $result;
    }
}
