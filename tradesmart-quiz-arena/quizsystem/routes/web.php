<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ReactionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'admin.dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {

    // ------------------------------------------------------------------ Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('questions/export', [QuestionController::class, 'export'])->name('questions.export');
        Route::get('questions/template', [QuestionController::class, 'template'])->name('questions.template');
        Route::post('questions/import', [QuestionController::class, 'import'])->name('questions.import');
        Route::patch('questions/{question}/toggle', [QuestionController::class, 'toggle'])->name('questions.toggle');
        Route::resource('questions', QuestionController::class)->except('show');

        Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::resource('categories', CategoryController::class)->except('show');

        Route::get('participants/export', [ParticipantController::class, 'export'])->name('participants.export');
        Route::get('participants/template', [ParticipantController::class, 'template'])->name('participants.template');
        Route::post('participants/import', [ParticipantController::class, 'import'])->name('participants.import');
        Route::patch('participants/{participant}/toggle', [ParticipantController::class, 'toggle'])->name('participants.toggle');
        Route::resource('participants', ParticipantController::class);

        Route::post('sessions/{session}/status/{action}', [SessionController::class, 'status'])
            ->whereIn('action', ['start', 'pause', 'resume', 'complete', 'reopen'])->name('sessions.status');
        Route::get('sessions/{session}/export', [SessionController::class, 'export'])->name('sessions.export');
        Route::get('sessions/{session}/report', [SessionController::class, 'report'])->name('sessions.report');
        Route::post('sessions/{session}/duplicate', [SessionController::class, 'duplicate'])->name('sessions.duplicate');
        Route::resource('sessions', SessionController::class);

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/participants.csv', [ReportController::class, 'participantsCsv'])->name('reports.participants');
        Route::get('reports/questions.csv', [ReportController::class, 'questionsCsv'])->name('reports.questions');

        Route::resource('reactions', ReactionController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::middleware('admin')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::resource('users', UserController::class)->except('show');
        });
    });

    // ------------------------------------------------------------------ Game arena
    Route::get('play/{session}', [GameController::class, 'show'])->name('play');
    Route::prefix('play/{session}/api')->name('play.')->middleware('throttle:game')->group(function () {
        Route::get('state', [GameController::class, 'state'])->name('state');
        Route::post('spin', [GameController::class, 'spin'])->name('spin');
        Route::post('score', [GameController::class, 'score'])->name('score');
        Route::post('undo', [GameController::class, 'undo'])->name('undo');
        Route::post('void', [GameController::class, 'void'])->name('void');
        Route::post('double', [GameController::class, 'double'])->name('double');
        Route::post('mode', [GameController::class, 'mode'])->name('mode');
        Route::post('status', [GameController::class, 'status'])->name('status');
        Route::get('final', [GameController::class, 'final'])->name('final');
        Route::post('settings', [GameController::class, 'settings'])->name('settings');
        Route::post('participants', [GameController::class, 'addParticipant'])->name('participants.add');
        Route::post('participants/attach', [GameController::class, 'attachParticipants'])->name('participants.attach');
        Route::patch('participants/{participant}', [GameController::class, 'updateParticipant'])->name('participants.update');
        Route::delete('participants/{participant}', [GameController::class, 'removeParticipant'])->name('participants.remove');
        Route::post('teams/generate', [GameController::class, 'generateTeams'])->name('teams.generate');
        Route::patch('teams/{team}', [GameController::class, 'updateTeam'])->name('teams.update');
        Route::get('questions', [GameController::class, 'questions'])->name('questions');
        Route::post('questions', [GameController::class, 'addQuestion'])->name('questions.add');
        Route::patch('questions/{question}', [GameController::class, 'updateQuestion'])->name('questions.update');
        Route::delete('questions/{question}', [GameController::class, 'deleteQuestion'])->name('questions.delete');
        Route::post('categories', [GameController::class, 'addCategory'])->name('categories.add');
        Route::patch('categories/{category}', [GameController::class, 'toggleCategory'])->name('categories.toggle');
    });
});
