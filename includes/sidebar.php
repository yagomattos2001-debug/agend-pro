<?php
/**
 * AGEND PRO - Sidebar de Navegação Administrativa
 */

declare(strict_types=1);

$currentPath = $_SERVER['REQUEST_URI'] ?? '';

function isNavActive(string $prefix, string $currentPath): bool
{
    return str_contains($currentPath, $prefix);
}
?>

<!-- Overlay para mobile -->
<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/60 z-40 hidden md:hidden transition-opacity"></div>

<!-- Sidebar Principal -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 border-r border-slate-800 flex flex-col justify-between transform -translate-x-full md:translate-x-0 md:static md:inset-auto transition-transform duration-200 ease-in-out">
    
    <!-- Top Brand Header -->
    <div>
        <div class="h-16 px-6 flex items-center justify-between border-b border-slate-800">
            <a href="/admin/dashboard.php" class="flex items-center gap-3 text-white font-bold tracking-tight">
                <div class="w-8 h-8 rounded-lg bg-teal-500 flex items-center justify-center font-extrabold text-slate-950 text-sm shadow-md shadow-teal-500/20">
                    AP
                </div>
                <div class="leading-none">
                    <span class="text-base text-white">AGEND <span class="text-teal-400">PRO</span></span>
                    <span class="block text-[10px] text-slate-400 font-semibold tracking-wider uppercase mt-0.5">Painel Gestor</span>
                </div>
            </a>
            <!-- Botão fechar no mobile -->
            <button type="button" id="btn-close-sidebar" class="md:hidden text-slate-400 hover:text-white p-1" aria-label="Fechar Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-4 space-y-1.5 text-sm font-medium">
            
            <!-- Dashboard -->
            <a href="/admin/dashboard.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= isNavActive('/admin/dashboard.php', $currentPath) ? 'bg-teal-500/10 text-teal-400 font-semibold border border-teal-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>

            <!-- Agendamentos -->
            <a href="/admin/agendamentos/index.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= isNavActive('/admin/agendamentos', $currentPath) ? 'bg-teal-500/10 text-teal-400 font-semibold border border-teal-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Agendamentos</span>
            </a>

            <div class="pt-3 pb-1.5 px-3.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                Cadastros & Regras
            </div>

            <!-- Profissionais -->
            <a href="/admin/profissionais/index.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= isNavActive('/admin/profissionais', $currentPath) ? 'bg-teal-500/10 text-teal-400 font-semibold border border-teal-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>Profissionais</span>
            </a>

            <!-- Serviços -->
            <a href="/admin/servicos/index.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= isNavActive('/admin/servicos', $currentPath) ? 'bg-teal-500/10 text-teal-400 font-semibold border border-teal-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <span>Serviços</span>
            </a>

            <!-- Horários de Trabalho -->
            <a href="/admin/horarios/index.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= isNavActive('/admin/horarios', $currentPath) ? 'bg-teal-500/10 text-teal-400 font-semibold border border-teal-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Horários de Trabalho</span>
            </a>

            <!-- Clientes -->
            <a href="/admin/clientes/index.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= isNavActive('/admin/clientes', $currentPath) ? 'bg-teal-500/10 text-teal-400 font-semibold border border-teal-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span>Clientes</span>
            </a>

        </nav>
    </div>

    <!-- Bottom Actions / Quick Links -->
    <div class="p-4 border-t border-slate-800 space-y-2">
        <a href="/public/index.php" target="_blank" class="flex items-center justify-between px-3.5 py-2 text-xs font-semibold text-slate-300 hover:text-white bg-slate-800/70 hover:bg-slate-800 rounded-xl border border-slate-700/60 transition-colors">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                Ver Agendamento Público
            </span>
            <span>&rarr;</span>
        </a>

        <a href="/logout.php" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-rose-400 hover:text-rose-300 hover:bg-rose-950/40 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            <span>Encerrar Sessão</span>
        </a>
    </div>

</aside>
