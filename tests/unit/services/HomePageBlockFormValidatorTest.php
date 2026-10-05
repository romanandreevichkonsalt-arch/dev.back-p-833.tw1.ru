<?php

namespace tests\unit\services;

use app\services\content\ContentPageBlockFormValidator;
use Codeception\Test\Unit;

class HomePageBlockFormValidatorTest extends Unit
{
    public function testCollectionsRequireSelectedDirectionWhenSlidesFilled(): void
    {
        $errors = ContentPageBlockFormValidator::validate('home', 'collections', [
            'cards' => [
                [
                    'catalog_direction_id' => '',
                    'slides' => [
                        ['label' => 'Диваны', 'image_src' => '/uploads/test.webp', 'image_alt' => ''],
                    ],
                ],
            ],
        ]);

        verify($errors)->notEmpty();
        verify($errors[0])->stringContainsString('Карточка 1');
        verify($errors[0])->stringContainsString('направление');
    }

    public function testCollectionsRejectLabelWithoutImage(): void
    {
        $errors = ContentPageBlockFormValidator::validate('home', 'collections', [
            'cards' => [
                [
                    'catalog_direction_id' => '',
                    'slides' => [
                        ['label' => 'Диваны', 'image_src' => '', 'image_alt' => ''],
                    ],
                ],
            ],
        ]);

        verify($errors)->notEmpty();
        verify(implode("\n", $errors))->stringContainsString('подпись без фото');
    }

    public function testCollectionsAllowEmptyCard(): void
    {
        $errors = ContentPageBlockFormValidator::validate('home', 'collections', [
            'cards' => [
                [
                    'catalog_direction_id' => '',
                    'slides' => [
                        ['label' => '', 'image_src' => '', 'image_alt' => ''],
                    ],
                ],
            ],
        ]);

        verify($errors)->equals([]);
    }

    public function testProductsRequirePickerSelection(): void
    {
        $errors = ContentPageBlockFormValidator::validate('home', 'products', [
            'cards' => [
                [
                    'catalog_product_id' => '',
                    'product_search' => 'Диван Турин',
                    'image_src' => '/uploads/test.webp',
                    'image_alt' => '',
                ],
            ],
        ]);

        verify($errors)->notEmpty();
        verify($errors[0])->stringContainsString('выберите товар');
    }

    public function testProductsRequireBannerWhenProductSelected(): void
    {
        $errors = ContentPageBlockFormValidator::validate('home', 'products', [
            'cards' => [
                [
                    'catalog_product_id' => '123',
                    'product_search' => 'Диван Турин',
                    'image_src' => '',
                    'image_alt' => '',
                ],
            ],
        ]);

        verify($errors)->notEmpty();
        verify($errors[0])->stringContainsString('баннер');
    }

    public function testHomePartnersRequireTitleAndImage(): void
    {
        $errors = ContentPageBlockFormValidator::validate('home', 'partners', [
            'cards' => [
                [
                    'title' => 'Партнёрам',
                    'image_src' => '',
                    'image_alt' => '',
                ],
            ],
        ]);

        verify($errors)->notEmpty();
        verify($errors[0])->stringContainsString('фото');
    }

    public function testVacanciesGroupsRequireJobTitle(): void
    {
        $errors = ContentPageBlockFormValidator::validate('vacancies', 'groups', [
            'groups' => [
                [
                    'jobs' => [
                        [
                            'department' => 'Цех',
                            'title' => '',
                            'description' => 'Описание',
                        ],
                    ],
                ],
            ],
        ]);

        verify($errors)->notEmpty();
        verify($errors[0])->stringContainsString('должность');
    }

    public function testContactsRegionsRequireBothCoordinates(): void
    {
        $errors = ContentPageBlockFormValidator::validate('contacts', 'regions', [
            'regions' => [
                [
                    'id' => 'moscow',
                    'name' => 'Москва',
                    'stores' => [
                        [
                            'name' => 'Салон',
                            'address' => 'ул. Пример, 1',
                            'lon' => '37.6',
                            'lat' => '',
                        ],
                    ],
                ],
            ],
        ]);

        verify($errors)->notEmpty();
        verify($errors[0])->stringContainsString('долготу');
    }
}
