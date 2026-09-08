<?php

namespace Tests\Unit;

use App\Rules\PasswordStandard;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordStandardTest extends TestCase
{
    public static function invalidPasswords(): array
    {
        return [
            'shorter than eight' => ['Aa1!aaa', 'Password must be at least 8 characters long.'],
            'missing uppercase' => ['lowercase1!', 'Password must contain at least one uppercase letter.'],
            'missing lowercase' => ['UPPERCASE1!', 'Password must contain at least one lowercase letter.'],
            'missing number' => ['Password!', 'Password must contain at least one number.'],
            'missing special character' => ['Password1', 'Password must contain at least one special character.'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_it_returns_the_specific_missing_requirement(string $password, string $message): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', new PasswordStandard]],
        );

        $this->assertTrue($validator->fails());
        $this->assertContains($message, $validator->errors()->get('password'));
    }

    public function test_it_accepts_a_password_that_meets_every_requirement(): void
    {
        $validator = Validator::make(
            ['password' => 'ValidPassword1!'],
            ['password' => ['required', new PasswordStandard]],
        );

        $this->assertFalse($validator->fails());
    }
}
