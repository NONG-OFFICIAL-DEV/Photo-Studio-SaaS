<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Scaffolded by `reverb:install` as `(int) $user->id === (int) $id` — every
 * PK in this app is a UUID string, so that cast silently collapses both
 * sides to 0 and would let ANY authenticated user subscribe to ANY other
 * user's private notification channel. Fixed to a plain string comparison.
 */
Broadcast::channel('App.Models.User.{id}', function ($user, string $id) {
    return (string) $user->id === $id;
});
