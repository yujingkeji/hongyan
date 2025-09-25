<?php

namespace App\JsonRpc;


interface RouteServiceInterface
{
    public function route(string|array $data);
}
