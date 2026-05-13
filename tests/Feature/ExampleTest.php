<?php

it('redirects root to login when unauthenticated', function () {
    $this->get('/')->assertRedirect(route('login'));
});
