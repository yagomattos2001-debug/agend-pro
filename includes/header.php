<?php
/**
 * AGEND PRO - Header Administrativo
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAuth();

$authUser = getAuthUser();
$flash = getFlashMessage();
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - AGEND PRO' : 'Painel Administrativo - AGEND PRO' ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">

    <div class="flex-1 flex min-h-screen">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            
            <!-- Top Navbar -->
            <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex items-center justify-between sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger Button -->
                    <button type="button" id="btn-mobile-menu" class="md:hidden p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer" aria-label="Abrir Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-lg font-bold text-slate-900 tracking-tight leading-tight">
                            <?= isset($pageTitle) ? sanitize($pageTitle) : 'Painel Administrativo' ?>
                        </h1>
                        <?php if (isset($pageSubtitle)): ?>
                            <p class="text-xs text-slate-500 hidden sm:block"><?= sanitize($pageSubtitle) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Header Actions -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php') ?>" target="_blank" rel="noopener noreferrer" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-100 border border-slate-200 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors" title="Abrir página atual em uma nova aba cheia fora do iframe">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        <span>Nova Aba</span>
                    </a>

                    <a href="/public/index.php" target="_blank" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 bg-teal-50 border border-teal-200 hover:bg-teal-100 px-3 py-1.5 rounded-lg transition-colors" title="Visualizar fluxo de agendamento do cliente">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        <span>Área Pública</span>
                    </a>

                    <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                        <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                            <?= strtoupper(substr($authUser['nome'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="hidden lg:block text-left text-xs leading-tight">
                            <div class="font-semibold text-slate-800"><?= sanitize($authUser['nome'] ?? 'Admin') ?></div>
                            <div class="text-slate-400"><?= sanitize($authUser['email'] ?? '') ?></div>
                        </div>
                        <a href="/logout.php" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors ml-1" title="Sair do Sistema">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Flash Messages Notification Banner -->
            <?php if ($flash): ?>
                <div class="px-4 sm:px-8 pt-4">
                    <div class="p-4 rounded-xl text-sm border flex items-center justify-between gap-3 shadow-sm animate-fade-in <?= $flash['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : ($flash['type'] === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800') ?>">
                        <div class="flex items-center gap-2.5">
                            <?php if ($flash['type'] === 'success'): ?>
                                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php elseif ($flash['type'] === 'error'): ?>
                                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php endif; ?>
                            <span class="font-medium"><?= sanitize($flash['message']) ?></span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">&times;</button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Inner Content -->
            <main class="flex-1 p-4 sm:p-8">
