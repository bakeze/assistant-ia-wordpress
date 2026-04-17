<?php

if (! defined('ABSPATH')) {
    exit;
}

interface ACE_Provider_Interface
{
    public function chat(array $payload): array;
}
