<?php

namespace tests\unit\services;

use app\services\dealer\DealerCredentialGenerator;

class DealerCredentialGeneratorTest extends \Codeception\Test\Unit
{
    public function testGeneratePasswordLength(): void
    {
        $generator = new DealerCredentialGenerator();
        verify(strlen($generator->generatePassword()))->equals(12);
    }

    public function testGenerateUsernameFromInn(): void
    {
        $generator = new DealerCredentialGenerator();
        verify($generator->generateUsername('7707083893'))->equals('d7707083893');
    }

    public function testGenerateUsernameWithoutInn(): void
    {
        $generator = new DealerCredentialGenerator();
        $username = $generator->generateUsername(null);

        verify($username)->startsWith('d');
        verify(strlen($username))->greaterThan(1);
    }
}
