<?php

namespace tests\unit\services;

use app\components\dadata\DaDataSuggestionFormatter;
use Codeception\Test\Unit;

class DaDataSuggestionFormatterTest extends Unit
{
    public function testFormatBuildsFullAddressWithPostalCode(): void
    {
        $formatted = DaDataSuggestionFormatter::format([
            'value' => 'г Москва, ул Тверская, д 7',
            'unrestricted_value' => 'г Москва, Тверской р-н, ул Тверская, д 7',
            'data' => [
                'postal_code' => '125009',
                'city_with_type' => 'г Москва',
                'city_fias_id' => '0c5b2444-70a3-4b20-878f-b0f2b8daecf0',
                'address_fias_id' => 'fias-1',
                'house_fias_id' => 'house-1',
                'geo_lat' => '55.7641',
                'geo_lon' => '37.6054',
            ],
        ]);

        $this->assertSame('г Москва, ул Тверская, д 7', $formatted['value']);
        $this->assertSame('125009, г Москва, Тверской р-н, ул Тверская, д 7', $formatted['full_address']);
        $this->assertSame('125009', $formatted['postal_code']);
        $this->assertSame('г Москва', $formatted['city_name']);
        $this->assertSame('0c5b2444-70a3-4b20-878f-b0f2b8daecf0', $formatted['data']['city_fias_id']);
    }
}
