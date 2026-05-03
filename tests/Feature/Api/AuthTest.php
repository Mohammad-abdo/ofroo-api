<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $role = Role::create(['name' => 'user']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+201010101010',
            'city' => 'القاهرة',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        $role = Role::create(['name' => 'user']);
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login(): void
    {
        $role = Role::create(['name' => 'user']);
        User::create([
            'name' => 'Test User',
            'email' => 'login@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $role = Role::create(['name' => 'user']);
        User::create([
            'name' => 'Test User',
            'email' => 'wrong@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_merchant_can_register(): void
    {
        $role = Role::create(['name' => 'merchant']);

        $response = $this->postJson('/api/auth/register-merchant', [
            'name' => 'Test Merchant',
            'email' => 'merchant@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company',
            'phone' => '+201234567890',
            'city' => 'القاهرة',
            'accepted_terms' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('merchants', [
            'company_name' => 'Test Company',
        ]);
    }

    public function test_existing_user_can_register_as_merchant_with_same_email_and_phone(): void
    {
        Role::create(['name' => 'user']);
        $merchantRole = Role::create(['name' => 'merchant']);

        $user = User::create([
            'name' => 'Ahmed',
            'email' => 'ahmed@test.com',
            'phone' => '+201111111111',
            'password' => 'password123',
            'role_id' => Role::where('name', 'user')->first()->id,
        ]);

        $response = $this->postJson('/api/auth/register-merchant', [
            'name' => 'أحمد محمد',
            'email' => 'ahmed@test.com',
            'phone' => '+201111111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name_ar' => 'متجر الاختبار',
            'company_name_en' => 'Test Shop',
            'city' => 'القاهرة',
            'accepted_terms' => true,
        ]);

        $response->assertStatus(201);
        $user->refresh();
        $this->assertSame($merchantRole->id, $user->role_id);
        $this->assertDatabaseHas('merchants', [
            'user_id' => $user->id,
            'company_name_ar' => 'متجر الاختبار',
        ]);
    }

    public function test_authentication_requires_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }
}
