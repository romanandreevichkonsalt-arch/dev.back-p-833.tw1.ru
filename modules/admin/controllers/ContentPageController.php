<?php

namespace app\modules\admin\controllers;

use app\models\ContentBlock;
use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageAboutHelper;
use app\modules\admin\helpers\ContentPageBuildingHelper;
use app\modules\admin\helpers\ContentPageContactsHelper;
use app\modules\admin\helpers\ContentPageDesignersHelper;
use app\modules\admin\helpers\ContentPageFaqHelper;
use app\modules\admin\helpers\ContentPageJournalHelper;
use app\modules\admin\helpers\ContentPageLibraryHelper;
use app\modules\admin\helpers\ContentPageVacanciesHelper;
use app\modules\admin\helpers\ContentPageHomeHelper;
use app\modules\admin\helpers\ContentPageHomeCollectionsHelper;
use app\modules\admin\helpers\ContentPageHomeHeroHelper;
use app\modules\admin\helpers\ContentPagePartnersHelper;
use app\modules\admin\helpers\ContentPagePrivacyPolicyHelper;
use app\modules\admin\helpers\ContentPageUserAgreementHelper;
use app\services\cache\ApiCacheInvalidator;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;
use app\services\content\ContentPageBlockFormValidator;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ContentPageController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('pages');

        return true;
    }

    public function actionIndex(): string
    {
        $pages = ContentPage::find()
            ->where(['slug' => ContentPage::adminIndexSlugs()])
            ->all();
        $pages = ContentPage::sortForAdminIndex($pages);

        return $this->render('index', ['pages' => $pages]);
    }

    public function actionBlocks(int $id): Response|string
    {
        $page = $this->findPage($id);

        if (ContentPageFaqHelper::usesUnifiedEditor($page->slug)) {
            return $this->faqUnifiedBlocksEditor($page);
        }

        if (ContentPageHomeHelper::usesUnifiedEditor($page->slug)) {
            return $this->homeUnifiedBlocksEditor($page);
        }

        if (ContentPagePartnersHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPagePartnersHelper::class, 'blocks-partners');
        }

        if (ContentPageDesignersHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageDesignersHelper::class, 'blocks-designers');
        }

        if (ContentPageContactsHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageContactsHelper::class, 'blocks-contacts');
        }

        if (ContentPageJournalHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageJournalHelper::class, 'blocks-journal');
        }

        if (ContentPageBuildingHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageBuildingHelper::class, 'blocks-building');
        }

        if (ContentPageAboutHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageAboutHelper::class, 'blocks-about');
        }

        if (ContentPageVacanciesHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageVacanciesHelper::class, 'blocks-vacancies');
        }

        if (ContentPageLibraryHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageLibraryHelper::class, 'blocks-library');
        }

        if (ContentPagePrivacyPolicyHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPagePrivacyPolicyHelper::class, 'blocks-privacy-policy');
        }

        if (ContentPageUserAgreementHelper::usesUnifiedEditor($page->slug)) {
            return $this->tabUnifiedBlocksEditor($page, ContentPageUserAgreementHelper::class, 'blocks-user-agreement');
        }

        $blocks = ContentBlock::find()
            ->where(['page_id' => $page->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        $blocks = ContentPageHomeHeroHelper::filterBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPageHomeCollectionsHelper::filterBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPagePartnersHelper::filterBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPagePartnersHelper::sortBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPageDesignersHelper::filterBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPageDesignersHelper::sortBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPageContactsHelper::filterBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPageContactsHelper::sortBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPageFaqHelper::filterBlocksForAdminList($page->slug, $blocks);
        $blocks = ContentPageFaqHelper::sortBlocksForAdminList($page->slug, $blocks);

        return $this->render('blocks', [
            'page' => $page,
            'blocks' => $blocks,
            'typeLabels' => BlockTypeRegistry::labels(),
        ]);
    }

    public function actionUpdateBlock(int $id): Response|string
    {
        $block = $this->findBlock($id);

        if (ContentPageFaqHelper::shouldRedirectToFaqPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageFaqHelper::tabForBlock($block)]);
        }

        if (ContentPageHomeHelper::shouldRedirectToHomeEditor($block)) {
            if (ContentPageHomeHeroHelper::isAutoManagedOnHome($block->page->slug, $block->block_key)) {
                \Yii::$app->session->setFlash(
                    'info',
                    'На главной автоматически показываются 3 последние статьи из страницы «Журнал».'
                );
            }

            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageHomeHelper::tabForBlock($block)]);
        }

        if (ContentPagePartnersHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPagePartnersHelper::tabForBlock($block)]);
        }

        if (ContentPageDesignersHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageDesignersHelper::tabForBlock($block)]);
        }

        if (ContentPageContactsHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageContactsHelper::tabForBlock($block)]);
        }

        if (ContentPageJournalHelper::shouldRedirectToJournalPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageJournalHelper::tabForBlock($block)]);
        }

        if (ContentPageBuildingHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageBuildingHelper::tabForBlock($block)]);
        }

        if (ContentPageAboutHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageAboutHelper::tabForBlock($block)]);
        }

        if (ContentPageVacanciesHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageVacanciesHelper::tabForBlock($block)]);
        }

        if (ContentPageLibraryHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => ContentPageLibraryHelper::tabForBlock($block)]);
        }

        if (ContentPagePrivacyPolicyHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id]);
        }

        if (ContentPageUserAgreementHelper::shouldRedirectToPageEditor($block)) {
            return $this->redirect(['blocks', 'id' => $block->page_id]);
        }

        if (ContentPageHomeHeroHelper::homeBlockEditorRedirectTarget($block) === 'blocks') {
            \Yii::$app->session->setFlash(
                'info',
                'На главной автоматически показываются 3 последние статьи из страницы «Журнал».'
            );

            return $this->redirect(['blocks', 'id' => $block->page_id, 'tab' => 'hero']);
        }

        $handler = new BlockFormHandler();
        $formData = $handler->dataToForm($block->block_type, $block->getDataArray());
        $formData = ContentPageHomeHeroHelper::mergeRelatedFormData($block, $handler, $formData);
        $formData = ContentPageHomeCollectionsHelper::mergeRelatedFormData($block, $handler, $formData);
        $formData = ContentPagePartnersHelper::mergeRelatedFormData($block, $handler, $formData);
        $formData = ContentPageDesignersHelper::mergeRelatedFormData($block, $handler, $formData);
        $formData = ContentPageContactsHelper::mergeRelatedFormData($block, $handler, $formData);
        $formData = ContentPageFaqHelper::mergeRelatedFormData($block, $handler, $formData);

        if (\Yii::$app->request->isPost) {
            $post = \Yii::$app->request->post();
            try {
                $data = $handler->dataFromPost($block->block_type, $post);
                $block->setDataArray($data);
                $block->is_active = (bool)($post['ContentBlock']['is_active'] ?? $block->is_active);
                $block->sort_order = (int)($post['ContentBlock']['sort_order'] ?? $block->sort_order);
                if ($block->save()) {
                    ContentPageHomeHeroHelper::saveRelatedBlocks($block, $handler, $post);
                    ContentPageHomeCollectionsHelper::saveRelatedBlocks($block, $handler, $post);
                    ContentPagePartnersHelper::saveRelatedBlocks($block, $handler, $post);
                    ContentPageDesignersHelper::saveRelatedBlocks($block, $handler, $post);
                    ContentPageContactsHelper::saveRelatedBlocks($block, $handler, $post);
                    ContentPageFaqHelper::saveRelatedBlocks($block, $handler, $post);
                    ApiCacheInvalidator::touch();
                    \Yii::$app->session->setFlash('success', 'Блок сохранён.');
                    return $this->redirect(['blocks', 'id' => $block->page_id]);
                }
            } catch (\JsonException) {
                \Yii::$app->session->setFlash('error', 'Некорректный JSON.');
            }
        }

        return $this->render('block-form', [
            'block' => $block,
            'page' => $block->page,
            'formData' => $formData,
            'typeLabel' => BlockTypeRegistry::labels()[$block->block_type] ?? $block->block_type,
            'isHomeHeroEditor' => ContentPageHomeHeroHelper::isHomeHeroBlock($block),
            'isHomeCollectionsEditor' => ContentPageHomeCollectionsHelper::isHomeCollectionsBlock($block),
            'isPartnersHeroEditor' => ContentPagePartnersHelper::isPartnersHeroBlock($block),
            'isPartnersIntroEditor' => ContentPagePartnersHelper::isPartnersIntroBlock($block),
            'isPartnersFormatsEditor' => ContentPagePartnersHelper::isPartnersFormatsBlock($block),
            'isPartnersAudienceEditor' => ContentPagePartnersHelper::isPartnersAudienceBlock($block),
            'isPartnersSalonFormatsEditor' => ContentPagePartnersHelper::isPartnersSalonFormatsBlock($block),
            'isPartnersPresentationEditor' => ContentPagePartnersHelper::isPartnersPresentationBlock($block),
            'isDesignersHeroEditor' => ContentPageDesignersHelper::isDesignersHeroBlock($block),
            'isDesignersIntroEditor' => ContentPageDesignersHelper::isDesignersIntroBlock($block),
            'isDesignersMaterialsEditor' => ContentPageDesignersHelper::isDesignersMaterialsBlock($block),
            'isDesignersGalleryEditor' => ContentPageDesignersHelper::isDesignersGalleryBlock($block),
            'isContactsContactEditor' => ContentPageContactsHelper::isContactsContactBlock($block),
            'isContactsHeroEditor' => ContentPageContactsHelper::isContactsHeroBlock($block),
            'isFaqHeroEditor' => ContentPageFaqHelper::isFaqHeroBlock($block),
            'isFaqIntroEditor' => ContentPageFaqHelper::isFaqIntroBlock($block),
            'isFaqCategoriesEditor' => ContentPageFaqHelper::isFaqCategoriesBlock($block),
        ]);
    }

    public function actionSearchCatalogModels(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        $request = \Yii::$app->request;
        $id = (int)$request->get('id', 0);
        if ($id > 0) {
            $item = HomePageProductsHelper::pickerItemByProductId($id);

            return $item ?? [];
        }

        return HomePageProductsHelper::searchProducts((string)$request->get('q', ''));
    }

    /**
     * @param class-string $helperClass
     */
    /**
     * @param class-string $helperClass
     */
    private function tabUnifiedBlocksEditor(ContentPage $page, string $helperClass, string $view): Response|string
    {
        $handler = new BlockFormHandler();
        $tab = $helperClass::normalizeTab((string)\Yii::$app->request->get('tab', 'hero'));
        $formData = $helperClass::buildFormDataForTab($handler, (int)$page->id, $tab);
        $postResult = $this->processUnifiedEditorPost(
            $page,
            $tab,
            $formData,
            static fn (array $post): bool => $helperClass::saveTab($handler, (int)$page->id, $tab, $post)
        );

        if ($postResult['redirect'] !== null) {
            return $this->redirect($postResult['redirect']);
        }

        return $this->render($view, [
            'page' => $page,
            'activeTab' => $tab,
            'formData' => $postResult['formData'],
        ]);
    }

    private function faqUnifiedBlocksEditor(ContentPage $page): Response|string
    {
        $handler = new BlockFormHandler();
        $tab = ContentPageFaqHelper::normalizeTab((string)\Yii::$app->request->get('tab', 'hero'));
        $formData = ContentPageFaqHelper::buildFormDataForTab($handler, (int)$page->id, $tab);
        $postResult = $this->processUnifiedEditorPost(
            $page,
            $tab,
            $formData,
            static fn (array $post): bool => ContentPageFaqHelper::saveTab($handler, (int)$page->id, $tab, $post)
        );

        if ($postResult['redirect'] !== null) {
            return $this->redirect($postResult['redirect']);
        }

        return $this->render('blocks-faq', [
            'page' => $page,
            'activeTab' => $tab,
            'formData' => $postResult['formData'],
        ]);
    }

    private function homeUnifiedBlocksEditor(ContentPage $page): Response|string
    {
        $handler = new BlockFormHandler();
        $tab = ContentPageHomeHelper::normalizeTab((string)\Yii::$app->request->get('tab', 'hero'));
        $formData = ContentPageHomeHelper::buildFormDataForTab($handler, (int)$page->id, $tab);
        $postResult = $this->processUnifiedEditorPost(
            $page,
            $tab,
            $formData,
            static fn (array $post): bool => ContentPageHomeHelper::saveTab($handler, (int)$page->id, $tab, $post)
        );

        if ($postResult['redirect'] !== null) {
            return $this->redirect($postResult['redirect']);
        }

        return $this->render('blocks-home', [
            'page' => $page,
            'activeTab' => $tab,
            'formData' => $postResult['formData'],
        ]);
    }

    /**
     * @param callable(array): bool $saveTab
     * @return array{formData: array<string, mixed>, redirect: array<string, mixed>|null}
     */
    private function processUnifiedEditorPost(
        ContentPage $page,
        string $tab,
        array $formData,
        callable $saveTab
    ): array {
        if (!\Yii::$app->request->isPost) {
            return ['formData' => $formData, 'redirect' => null];
        }

        $post = \Yii::$app->request->post();

        try {
            $validationErrors = ContentPageBlockFormValidator::validate($page->slug, $tab, $post);
            if ($validationErrors !== []) {
                \Yii::$app->session->setFlash('error', self::formatAdminValidationFlash($validationErrors));

                return [
                    'formData' => ContentPageBlockFormValidator::formDataFromPost($page->slug, $tab, $post),
                    'redirect' => null,
                ];
            }

            if ($saveTab($post)) {
                ApiCacheInvalidator::touch();
                \Yii::$app->session->setFlash('success', 'Блок сохранён.');

                return [
                    'formData' => $formData,
                    'redirect' => ['blocks', 'id' => $page->id, 'tab' => $tab],
                ];
            }

            \Yii::$app->session->setFlash('error', 'Не удалось сохранить блок. Проверьте заполнение полей.');

            return [
                'formData' => ContentPageBlockFormValidator::formDataFromPost($page->slug, $tab, $post),
                'redirect' => null,
            ];
        } catch (\JsonException) {
            \Yii::$app->session->setFlash('error', 'Некорректный JSON.');

            return [
                'formData' => ContentPageBlockFormValidator::formDataFromPost($page->slug, $tab, $post),
                'redirect' => null,
            ];
        }
    }

    /**
     * @param string[] $errors
     */
    private static function formatAdminValidationFlash(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        if (count($errors) === 1) {
            return $errors[0];
        }

        $items = array_map(
            static fn (string $error): string => '<li>' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>',
            $errors
        );

        return '<strong>Исправьте ошибки перед сохранением:</strong><ul class="admin-flash-list">'
            . implode('', $items)
            . '</ul>';
    }

    private function findPage(int $pageId): ContentPage
    {
        $page = ContentPage::findOne($pageId);
        if ($page === null) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        return $page;
    }

    private function findBlock(int $id): ContentBlock
    {
        $block = ContentBlock::find()->where(['id' => $id])->with('page')->one();
        if ($block === null) {
            throw new NotFoundHttpException('Блок не найден.');
        }

        return $block;
    }
}
