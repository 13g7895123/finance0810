<?php

require_once __DIR__ . '/backend/vendor/autoload.php';

$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('username', 'admin')->first();

if ($user) {
    $user->password = Hash::make('admin123');
    $user->save();
    echo "Admin password updated to admin123\n";
    echo "User details:\n";
    echo "ID: " . $user->id . "\n";
    echo "Username: " . $user->username . "\n";
    echo "Email: " . $user->email . "\n";
} else {
    echo "Admin user not found\n";
}