/**
 * Professional Modal Component
 */
class ProfessionalModal {
    constructor() {
        this.createModal();
        this.bindEvents();
    }

    createModal() {
        // Create modal HTML structure
        const modalHTML = `
            <div id="professional-modal" class="professional-modal" style="display: none;">
                <div class="professional-modal-overlay"></div>
                <div class="professional-modal-container">
                    <div class="professional-modal-header">
                        <div class="modal-icon-container">
                            <i id="modal-icon" class="modal-icon"></i>
                        </div>
                        <h3 id="professional-modal-title">Notification</h3>
                        <button id="professional-modal-close" class="modal-close-btn">
                            <i class="icon-close">×</i>
                        </button>
                    </div>
                    <div class="professional-modal-body">
                        <p id="professional-modal-message">Message content</p>
                        <div id="professional-modal-details" class="modal-details" style="display: none;"></div>
                    </div>
                    <div class="professional-modal-footer">
                        <button id="professional-modal-cancel" class="btn btn-secondary" style="display: none;">Cancel</button>
                        <button id="professional-modal-confirm" class="btn btn-primary">OK</button>
                    </div>
                </div>
            </div>
        `;

        // Add styles
        const styles = `
            <style>
                .professional-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .professional-modal-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(2px);
                }

                .professional-modal-container {
                    position: relative;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow: hidden;
                    animation: modalSlideIn 0.3s ease-out;
                }

                @keyframes modalSlideIn {
                    from {
                        opacity: 0;
                        transform: scale(0.8) translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: scale(1) translateY(0);
                    }
                }

                .professional-modal-header {
                    padding: 20px 25px 15px;
                    border-bottom: 1px solid #e9ecef;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }

                .modal-icon-container {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                }

                .modal-icon-container.success {
                    background: #d4edda;
                    color: #155724;
                }

                .modal-icon-container.error {
                    background: #f8d7da;
                    color: #721c24;
                }

                .modal-icon-container.warning {
                    background: #fff3cd;
                    color: #856404;
                }

                .modal-icon-container.info {
                    background: #d1ecf1;
                    color: #0c5460;
                }

                .modal-icon-container.confirm {
                    background: #e2e3e5;
                    color: #383d41;
                }

                #professional-modal-title {
                    flex: 1;
                    margin: 0;
                    font-size: 18px;
                    font-weight: 600;
                    color: #2c3e50;
                }

                .modal-close-btn {
                    background: none;
                    border: none;
                    font-size: 20px;
                    color: #6c757d;
                    cursor: pointer;
                    padding: 5px;
                    line-height: 1;
                    border-radius: 4px;
                    transition: all 0.2s ease;
                }

                .modal-close-btn:hover {
                    background: #f8f9fa;
                    color: #495057;
                }

                .professional-modal-body {
                    padding: 20px 25px;
                }

                #professional-modal-message {
                    margin: 0 0 15px 0;
                    font-size: 16px;
                    line-height: 1.5;
                    color: #495057;
                }

                .modal-details {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 6px;
                    padding: 15px;
                    font-size: 14px;
                    color: #6c757d;
                }

                .professional-modal-footer {
                    padding: 15px 25px 20px;
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                    border-top: 1px solid #e9ecef;
                }

                .professional-modal .btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    min-width: 80px;
                }

                .professional-modal .btn-primary {
                    background: #007bff;
                    color: white;
                }

                .professional-modal .btn-primary:hover {
                    background: #0056b3;
                }

                .professional-modal .btn-secondary {
                    background: #6c757d;
                    color: white;
                }

                .professional-modal .btn-secondary:hover {
                    background: #545b62;
                }
            </style>
        `;

        // Add to document
        document.head.insertAdjacentHTML('beforeend', styles);
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        this.modal = document.getElementById('professional-modal');
        this.overlay = document.querySelector('.professional-modal-overlay');
        this.closeBtn = document.getElementById('professional-modal-close');
        this.cancelBtn = document.getElementById('professional-modal-cancel');
        this.confirmBtn = document.getElementById('professional-modal-confirm');
    }

    bindEvents() {
        // Close events
        this.closeBtn.addEventListener('click', () => this.hide());
        this.overlay.addEventListener('click', () => this.hide());
        this.cancelBtn.addEventListener('click', () => this.hide());

        // ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.style.display !== 'none') {
                this.hide();
            }
        });
    }

    show(options = {}) {
        const {
            title = 'Notification',
            message = '',
            details = '',
            type = 'info', // success, error, warning, info, confirm
            showCancel = false,
            confirmText = 'OK',
            cancelText = 'Cancel',
            onConfirm = null,
            onCancel = null
        } = options;

        // Set content
        document.getElementById('professional-modal-title').textContent = title;
        document.getElementById('professional-modal-message').textContent = message;

        // Set details if provided
        const detailsEl = document.getElementById('professional-modal-details');
        if (details) {
            detailsEl.textContent = details;
            detailsEl.style.display = 'block';
        } else {
            detailsEl.style.display = 'none';
        }

        // Set icon and type
        const iconContainer = document.querySelector('.modal-icon-container');
        const iconEl = document.getElementById('modal-icon');
        
        // Remove all type classes
        iconContainer.className = 'modal-icon-container';
        iconContainer.classList.add(type);

        // Set icon based on type
        const icons = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'ℹ',
            confirm: '?'
        };
        iconEl.textContent = icons[type] || icons.info;

        // Configure buttons
        this.cancelBtn.style.display = showCancel ? 'block' : 'none';
        this.confirmBtn.textContent = confirmText;
        this.cancelBtn.textContent = cancelText;

        // Set up callbacks
        this.onConfirm = onConfirm;
        this.onCancel = onCancel;

        // Remove existing event listeners
        const newConfirmBtn = this.confirmBtn.cloneNode(true);
        const newCancelBtn = this.cancelBtn.cloneNode(true);
        this.confirmBtn.parentNode.replaceChild(newConfirmBtn, this.confirmBtn);
        this.cancelBtn.parentNode.replaceChild(newCancelBtn, this.cancelBtn);
        this.confirmBtn = newConfirmBtn;
        this.cancelBtn = newCancelBtn;

        // Add new event listeners
        this.confirmBtn.addEventListener('click', () => {
            this.hide();
            if (this.onConfirm) this.onConfirm();
        });

        this.cancelBtn.addEventListener('click', () => {
            this.hide();
            if (this.onCancel) this.onCancel();
        });

        // Show modal
        this.modal.style.display = 'flex';
        
        // Focus confirm button
        setTimeout(() => this.confirmBtn.focus(), 100);
    }

    hide() {
        this.modal.style.display = 'none';
    }

    // Static convenience methods
    static success(message, title = 'Success', options = {}) {
        if (!window.professionalModal) {
            window.professionalModal = new ProfessionalModal();
        }
        window.professionalModal.show({
            title,
            message,
            type: 'success',
            ...options
        });
    }

    static error(message, title = 'Error', options = {}) {
        if (!window.professionalModal) {
            window.professionalModal = new ProfessionalModal();
        }
        window.professionalModal.show({
            title,
            message,
            type: 'error',
            ...options
        });
    }

    static warning(message, title = 'Warning', options = {}) {
        if (!window.professionalModal) {
            window.professionalModal = new ProfessionalModal();
        }
        window.professionalModal.show({
            title,
            message,
            type: 'warning',
            ...options
        });
    }

    static info(message, title = 'Information', options = {}) {
        if (!window.professionalModal) {
            window.professionalModal = new ProfessionalModal();
        }
        window.professionalModal.show({
            title,
            message,
            type: 'info',
            ...options
        });
    }

    static confirm(message, title = 'Confirm', options = {}) {
        if (!window.professionalModal) {
            window.professionalModal = new ProfessionalModal();
        }
        window.professionalModal.show({
            title,
            message,
            type: 'confirm',
            showCancel: true,
            ...options
        });
    }
}

// Initialize global modal instance
document.addEventListener('DOMContentLoaded', () => {
    if (!window.professionalModal) {
        window.professionalModal = new ProfessionalModal();
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProfessionalModal;
}