            </div><!-- Fin content-wrapper -->
        </div><!-- Fin main-content -->
    </div><!-- Fin app-wrapper -->
    
    <!-- Modal pour les confirmations -->
    <div id="confirmModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Confirmation</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                Êtes-vous sûr de vouloir effectuer cette action ?
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="modalCancel">Annuler</button>
                <button class="btn btn-primary" id="modalConfirm">Confirmer</button>
            </div>
        </div>
    </div>
    
    <!-- Modal pour les formulaires rapides -->
    <div id="quickModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 id="quickModalTitle">Action rapide</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="quickModalBody">
                <!-- Contenu dynamique -->
            </div>
        </div>
    </div>
    
    <!-- Container pour les notifications toast -->
    <div id="toastContainer" class="toast-container"></div>
    
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="spinner">
            <div class="spinner-border"></div>
            <p>Chargement...</p>
        </div>
    </div>
    
    <!-- Scripts JavaScript -->
    <script>
        // Configuration globale
        const APP_URL = '<?= APP_URL ?>';
        const USER_ROLE = '<?= $_SESSION['role'] ?? '' ?>';
        const USER_ID = '<?= $_SESSION['user_id'] ?? '' ?>';
        const CSRF_TOKEN = '<?= generateCsrfToken() ?>';
    </script>
    
    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
    <script src="<?= APP_URL ?>/assets/js/dashboard.js"></script>
    <script src="<?= APP_URL ?>/assets/js/validation.js"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?= $additionalScripts ?>
    <?php endif; ?>
    
    <script>
        // Initialisation des composants
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser les tooltips
            initTooltips();
            
            // Initialiser la recherche globale
            initGlobalSearch();
            
            // Initialiser les notifications
            initNotifications();
            
            // Initialiser le profil dropdown
            initProfileDropdown();
            
            // Auto-hide les alerts après 5 secondes
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(function(alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
        
        // Fonctions d'initialisation
        function initTooltips() {
            document.querySelectorAll('[data-tooltip]').forEach(element => {
                element.addEventListener('mouseenter', showTooltip);
                element.addEventListener('mouseleave', hideTooltip);
            });
        }
        
        function initGlobalSearch() {
            const searchInput = document.getElementById('globalSearch');
            const suggestions = document.getElementById('searchSuggestions');
            
            if (searchInput) {
                let timeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(timeout);
                    const query = this.value;
                    
                    if (query.length >= 3) {
                        timeout = setTimeout(() => {
                            fetchSearchSuggestions(query);
                        }, 300);
                    } else {
                        suggestions.style.display = 'none';
                    }
                });
                
                // Fermer les suggestions en cliquant ailleurs
                document.addEventListener('click', function(e) {
                    if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
                        suggestions.style.display = 'none';
                    }
                });
            }
        }
        
        function fetchSearchSuggestions(query) {
            fetch(`${APP_URL}/commun/recherche_ajax.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    const suggestions = document.getElementById('searchSuggestions');
                    const list = suggestions.querySelector('.suggestions-list');
                    
                    if (data.length > 0) {
                        list.innerHTML = data.map(item => `
                            <a href="${APP_URL}/commun/vue_compte.php?id=${item.id_compte}" class="suggestion-item">
                                <div class="suggestion-icon">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div class="suggestion-content">
                                    <div class="suggestion-title">${item.nom} ${item.prenom}</div>
                                    <div class="suggestion-subtitle">
                                        Compte: ${item.id_compte} • ${item.id_client}
                                    </div>
                                </div>
                                <div class="suggestion-balance">
                                    ${formatMoney(item.solde)}
                                </div>
                            </a>
                        `).join('');
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                });
        }
        
        function initNotifications() {
            const btn = document.getElementById('notificationBtn');
            const menu = document.getElementById('notificationsMenu');
            
            if (btn && menu) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    menu.classList.toggle('show');
                });
                
                document.addEventListener('click', function(e) {
                    if (!btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.remove('show');
                    }
                });
            }
        }
        
        function initProfileDropdown() {
            const btn = document.getElementById('userProfileBtn');
            const dropdown = document.getElementById('profileDropdown');
            
            if (btn && dropdown) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('show');
                });
                
                document.addEventListener('click', function(e) {
                    if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        }
        
        // Fonctions utilitaires
        function formatMoney(amount) {
            return new Intl.NumberFormat('fr-HT', {
                style: 'currency',
                currency: 'HTG'
            }).format(amount);
        }
        
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };
            
            toast.innerHTML = `
                <i class="fas fa-${icons[type]}"></i>
                <span>${message}</span>
                <button class="toast-close">&times;</button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
            
            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.remove();
            });
        }
        
        function confirmAction(message, callback) {
            const modal = document.getElementById('confirmModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            const confirmBtn = document.getElementById('modalConfirm');
            const cancelBtn = document.getElementById('modalCancel');
            const closeBtn = modal.querySelector('.modal-close');
            
            title.textContent = 'Confirmation';
            body.textContent = message;
            modal.style.display = 'flex';
            
            const closeModal = () => {
                modal.style.display = 'none';
            };
            
            confirmBtn.onclick = () => {
                callback();
                closeModal();
            };
            
            cancelBtn.onclick = closeModal;
            closeBtn.onclick = closeModal;
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
        }
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // Gestion des formulaires AJAX
        function submitAjaxForm(form, successCallback) {
            const formData = new FormData(form);
            formData.append('csrf_token', CSRF_TOKEN);
            
            showLoading();
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message || 'Opération réussie', 'success');
                    if (successCallback) successCallback(data);
                } else {
                    showToast(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Erreur de connexion', 'error');
            });
        }
        
        // Validation des formulaires
        function validateForm(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
    </script>
</body>
</html>