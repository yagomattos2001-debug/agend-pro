<?php
/**
 * AGEND PRO - Footer Administrativo
 */

declare(strict_types=1);
?>
            </main>

            <!-- Bottom System Information Footer -->
            <footer class="bg-white border-t border-slate-200 px-4 sm:px-8 py-4 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div>
                    <span class="font-semibold text-slate-700">AGEND PRO</span> &bull; Sistema de Gestão de Agendamentos (PHP 8 + MySQL PDO)
                </div>
                <div class="flex items-center gap-4 text-slate-400">
                    <span>Versão 1.0</span>
                    <span>&bull;</span>
                    <a href="/public/index.php" target="_blank" class="hover:text-teal-600 transition-colors">Testar Agendamento</a>
                </div>
            </footer>

        </div>
    </div>

    <!-- Script para controle de responsividade (Sidebar Mobile), Modais e Persistência de Sessão em iframes -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnOpenMobile = document.getElementById('btn-mobile-menu');
            const btnCloseMobile = document.getElementById('btn-close-sidebar');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            function toggleSidebar(show) {
                if (!sidebar || !overlay) return;
                if (show) {
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.remove('hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                }
            }

            btnOpenMobile?.addEventListener('click', () => toggleSidebar(true));
            btnCloseMobile?.addEventListener('click', () => toggleSidebar(false));
            overlay?.addEventListener('click', () => toggleSidebar(false));

            // Sincronização de sessão em iframe caso o navegador bloqueie cookies de terceiros
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const sidUrl = urlParams.get('sid');
                if (sidUrl) {
                    sessionStorage.setItem('agendpro_sid', sidUrl);
                }
                const savedSid = sessionStorage.getItem('agendpro_sid');
                if (savedSid) {
                    document.querySelectorAll('a[href^="/admin/"]').forEach(link => {
                        const href = link.getAttribute('href');
                        if (href && !href.includes('sid=')) {
                            const sep = href.includes('?') ? '&' : '?';
                            link.setAttribute('href', href + sep + 'sid=' + encodeURIComponent(savedSid));
                        }
                    });
                    document.querySelectorAll('form[action^="/admin/"]').forEach(form => {
                        if (!form.querySelector('input[name="sid"]')) {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'sid';
                            hidden.value = savedSid;
                            form.appendChild(hidden);
                        }
                    });
                }
            } catch (e) {
                // Ignore storage errors in restricted contexts
            }
        });
    </script>
</body>
</html>
