<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polis\Plugin\Voting\Http\Controllers\VotingSessionController;

/*
|--------------------------------------------------------------------------
| Voting plugin routes
|--------------------------------------------------------------------------
|
| Loaded by the polis-laravel PluginManager under the prefix + middleware the
| plugin declares in VotingPlugin::register() (default prefix "voting",
| middleware "api-v1"). The four core flows plus skip/complete/rankings.
|
| {session} binds the plugin's VotingSession model by route-model binding.
|
*/

Route::post('sessions', [VotingSessionController::class, 'start'])->name('voting.sessions.start');
Route::get('sessions/{session}/next', [VotingSessionController::class, 'next'])->name('voting.sessions.next');
Route::post('sessions/{session}/vote', [VotingSessionController::class, 'vote'])->name('voting.sessions.vote');
Route::post('sessions/{session}/skip', [VotingSessionController::class, 'skip'])->name('voting.sessions.skip');
Route::post('sessions/{session}/complete', [VotingSessionController::class, 'complete'])->name('voting.sessions.complete');
Route::get('rankings', [VotingSessionController::class, 'rankings'])->name('voting.rankings');
