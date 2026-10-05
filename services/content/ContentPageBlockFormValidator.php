<?php

namespace app\services\content;

use app\modules\admin\helpers\ContentPageHomeHelper;
use app\modules\admin\helpers\ContentPagePrivacyPolicyHelper;
use app\modules\admin\helpers\ContentPageUserAgreementHelper;
use app\services\content\validators\ContentPageBlockValidators;

class ContentPageBlockFormValidator
{
    /**
     * @return string[]
     */
    public static function validate(string $pageSlug, string $tab, array $post): array
    {
        return match ($pageSlug) {
            'home' => ContentPageBlockValidators::home($tab, $post),
            'partners' => ContentPageBlockValidators::partners($tab, $post),
            'designers' => ContentPageBlockValidators::designers($tab, $post),
            'contacts' => ContentPageBlockValidators::contacts($tab, $post),
            'faq' => ContentPageBlockValidators::faq($tab, $post),
            'journal' => ContentPageBlockValidators::journal($tab, $post),
            'building' => ContentPageBlockValidators::building($tab, $post),
            'about' => ContentPageBlockValidators::about($tab, $post),
            'vacancies' => ContentPageBlockValidators::vacancies($tab, $post),
            'library' => [],
            'privacy-policy' => [],
            'user-agreement' => [],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDataFromPost(string $pageSlug, string $tab, array $post): array
    {
        if ($pageSlug === 'home') {
            return ContentPageHomeHelper::formDataFromPost($tab, $post);
        }

        if ($pageSlug === 'privacy-policy') {
            return ContentPagePrivacyPolicyHelper::formDataFromPost($post);
        }

        if ($pageSlug === 'user-agreement') {
            return ContentPageUserAgreementHelper::formDataFromPost($post);
        }

        return $post;
    }
}
