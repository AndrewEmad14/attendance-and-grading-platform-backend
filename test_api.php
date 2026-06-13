<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Requests\ListInstructorsRequest;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::where('email', 'admin@example.com')->first();
auth()->login($user);

$request = Request::create('/api/instructors', 'GET', ['cohort_id' => 1]);
$request->setUserResolver(function () use ($user) {
    return $user;
});

$controller = new UserController;
try {
    $response = $controller->listInstructors(ListInstructorsRequest::createFrom($request));
    echo json_encode($response->getData(true)['data']['data'] ?? $response->getData(true));
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage();
}
