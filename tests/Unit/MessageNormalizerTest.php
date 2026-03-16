<?php

use LaraSpan\Client\Support\MessageNormalizer;

it('replaces integers with [int]', function () {
    expect(MessageNormalizer::normalize('User 42 not found'))
        ->toBe('User [int] not found');
});

it('replaces UUIDs with [uuid]', function () {
    expect(MessageNormalizer::normalize('Record 550e8400-e29b-41d4-a716-446655440000 missing'))
        ->toBe('Record [uuid] missing');
});

it('replaces emails with [email]', function () {
    expect(MessageNormalizer::normalize('Failed for user john@example.com'))
        ->toBe('Failed for user [email]');
});

it('handles multiple replacements', function () {
    $input = 'User 123 with email test@foo.com and id 550e8400-e29b-41d4-a716-446655440000';
    $result = MessageNormalizer::normalize($input);

    expect($result)
        ->toContain('[int]')
        ->toContain('[email]')
        ->toContain('[uuid]')
        ->not->toContain('123')
        ->not->toContain('test@foo.com');
});

it('returns original string when nothing to normalize', function () {
    expect(MessageNormalizer::normalize('Something went wrong'))
        ->toBe('Something went wrong');
});

it('handles empty string', function () {
    expect(MessageNormalizer::normalize(''))->toBe('');
});
