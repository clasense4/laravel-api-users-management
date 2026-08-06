<?php

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<int, array{string, string, bool}>
 */
function policyMatrix(): array
{
    return [
        // [actor_factory_state, target_factory_state, expected_can_edit]
        ['administrator', 'administrator', true],
        ['administrator', 'manager', true],
        ['administrator', null, true],        // null = default (role=user)
        ['manager', null, true],              // manager → user target
        ['manager', 'manager', false],
        ['manager', 'administrator', false],
    ];
}

test('policy returns correct result for role combinations', function (?string $actorState, ?string $targetState, bool $expected): void {
    $actor = $actorState
        ? User::factory()->{$actorState}()->create()
        : User::factory()->create();

    $target = $targetState
        ? User::factory()->{$targetState}()->create()
        : User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->update($actor, $target))->toBe($expected);
})->with(policyMatrix());

test('user can edit themselves', function (): void {
    $user = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->update($user, $user))->toBeTrue();
});

test('user cannot edit another user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->update($user, $other))->toBeFalse();
});

test('user cannot edit a manager', function (): void {
    $user = User::factory()->create();
    $manager = User::factory()->manager()->create();
    $policy = new UserPolicy;

    expect($policy->update($user, $manager))->toBeFalse();
});

test('user cannot edit an administrator', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->administrator()->create();
    $policy = new UserPolicy;

    expect($policy->update($user, $admin))->toBeFalse();
});
