<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@example.com')->first();
auth()->login($user);

$request = Illuminate\Http\Request::create('/api/instructors', 'GET', ['cohort_id' => 1]);
$request->setUserResolver(function () use ($user) { return $user; });

$controller = new App\Http\Controllers\Api\UserController();
try {
    $response = $controller->listInstructors(App\Http\Requests\ListInstructorsRequest::createFrom($request));
    echo json_encode($response->getData(true)['data']['data'] ?? $response->getData(true));
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
