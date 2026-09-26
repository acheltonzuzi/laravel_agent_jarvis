<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('guests cannot access user management', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));
});

test('verified users can manage user accounts', function () {
    $manager = User::factory()->create();
    $this->actingAs($manager);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Utilizadores');

    $component = Livewire::test('pages::users')
        ->set('name', 'New User')
        ->set('email', 'new-user@example.com')
        ->set('password', 'correct-horse-battery')
        ->set('password_confirmation', 'correct-horse-battery')
        ->call('save')
        ->assertHasNoErrors();

    $createdUser = User::where('email', 'new-user@example.com')->firstOrFail();

    expect(Hash::check('correct-horse-battery', $createdUser->password))->toBeTrue();

    $component
        ->call('edit', $createdUser->id)
        ->set('name', 'Updated User')
        ->set('email', 'updated-user@example.com')
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($createdUser->refresh()->name)->toBe('Updated User')
        ->and($createdUser->email)->toBe('updated-user@example.com');
});

test('users cannot delete their own account through user management', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::users')
        ->call('confirmDelete', $user->id)
        ->call('delete')
        ->assertStatus(403);

    expect($user->fresh())->not->toBeNull();
});