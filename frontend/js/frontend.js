/**
 * RatNotes Frontend JavaScript
 *
 * @package RatNotes
 */

(function($) {
    'use strict';

    /**
     * Main application object.
     */
    const RatNotesFrontend = {
        notes: [],
        currentNote: null,
        currentStatus: 'active',
        isLoading: false,
        $container: null,

        /**
         * Initialize the application.
         */
        init: function() {
            this.$container = $('.ratnotes-frontend');
            if (!this.$container.length) return;

            this.currentStatus = this.$container.data('status') || 'active';
            this.bindEvents();
            this.loadNotes();
        },

        /**
         * Bind event listeners.
         */
        bindEvents: function() {
            // Navigation
            this.$container.on('click', '.ratnotes-frontend-nav-item', (e) => this.handleNavClick(e));

            // Search
            this.$container.on('input', '.ratnotes-frontend-search-input', (e) => this.handleSearch(e));

            // Create button
            this.$container.on('click', '.ratnotes-frontend-create-btn', () => this.openModal());

            // Modal close
            this.$container.on('click', '.ratnotes-frontend-modal-close, .ratnotes-frontend-modal-overlay', () => this.closeModal());

            // Save button
            this.$container.on('click', '.ratnotes-frontend-save-btn', () => this.saveNote());

            // Delete button
            this.$container.on('click', '.ratnotes-frontend-delete-btn', () => this.deleteNote());

            // Archive button
            this.$container.on('click', '.ratnotes-frontend-archive-btn', () => this.archiveNote());

            // Pin button
            this.$container.on('click', '.ratnotes-frontend-pin-btn', () => this.togglePin());

            // Color picker
            this.$container.on('click', '.ratnotes-frontend-color-btn', (e) => this.selectColor(e));

            // Note card click
            this.$container.on('click', '.ratnotes-frontend-note', (e) => {
                if (!$(e.target).closest('.ratnotes-frontend-actions').length) {
                    const noteId = $(e.currentTarget).data('id');
                    this.openModal(noteId);
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => this.handleKeyboard(e));
        },

        /**
         * Load notes from API.
         */
        loadNotes: async function() {
            if (this.isLoading) return;

            this.isLoading = true;
            this.$container.find('.ratnotes-frontend-loading').show();

            try {
                const response = await $.ajax({
                    url: ratnotesFrontendData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ratnotes_get_notes',
                        nonce: ratnotesFrontendData.nonce,
                        status: this.currentStatus,
                        search: this.$container.find('.ratnotes-frontend-search-input').val() || ''
                    }
                });

                if (response.success) {
                    this.notes = response.data;
                    this.renderNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to load notes');
                }
            } catch (error) {
                console.error('Error loading notes:', error);
                this.showError('Failed to load notes');
            } finally {
                this.isLoading = false;
                this.$container.find('.ratnotes-frontend-loading').hide();
            }
        },

        /**
         * Render notes grid.
         */
        renderNotes: function(notesToRender = null) {
            const $grid = this.$container.find('.ratnotes-frontend-grid');
            const notes = notesToRender || this.notes;

            if (notes.length === 0) {
                $grid.html(this.renderEmptyState());
                return;
            }

            // Sort: pinned first
            const sortedNotes = [...notes].sort((a, b) => {
                if (a.is_pinned && !b.is_pinned) return -1;
                if (!a.is_pinned && b.is_pinned) return 1;
                return new Date(b.updated_at) - new Date(a.updated_at);
            });

            $grid.html(
                sortedNotes.map(note => this.renderNoteCard(note)).join('')
            );
        },

        /**
         * Render a single note card.
         */
        renderNoteCard: function(note) {
            const pinnedClass = note.is_pinned ? 'pinned' : '';
            const labels = (note.labels || []).map(label =>
                `<span class="ratnotes-frontend-label">${this.escapeHtml(label)}</span>`
            ).join('');

            return `
                <div class="ratnotes-frontend-note ${pinnedClass}"
                     data-id="${note.id}"
                     style="background-color: ${this.escapeHtml(note.color)}">
                    ${note.title ? `<div class="ratnotes-frontend-note-title">${this.escapeHtml(note.title)}</div>` : ''}
                    <div class="ratnotes-frontend-note-content">${this.escapeHtml(note.content)}</div>
                    ${labels ? `<div class="ratnotes-frontend-note-labels">${labels}</div>` : ''}
                </div>
            `;
        },

        /**
         * Render empty state.
         */
        renderEmptyState: function() {
            const messages = {
                active: {
                    icon: 'dashicons-admin-notes',
                    title: ratnotesFrontendData.strings.createNote,
                    text: 'Click "New Note" to create your first note'
                },
                archived: {
                    icon: 'dashicons-archive',
                    title: 'No archived notes',
                    text: 'Archived notes will appear here'
                },
                trash: {
                    icon: 'dashicons-trash',
                    title: 'Trash is empty',
                    text: 'Deleted notes will appear here'
                }
            };

            const msg = messages[this.currentStatus] || messages.active;

            return `
                <div class="ratnotes-frontend-empty">
                    <span class="dashicons ${msg.icon}"></span>
                    <h3>${msg.title}</h3>
                    <p>${msg.text}</p>
                </div>
            `;
        },

        /**
         * Open modal for create/edit.
         */
        openModal: function(noteId = null) {
            if (!ratnotesFrontendData.isLoggedIn) {
                this.showError(ratnotesFrontendData.strings.loginRequired);
                return;
            }

            this.currentNote = noteId ? this.notes.find(n => n.id === noteId) : null;

            const $modal = this.$container.find('.ratnotes-frontend-modal');
            const $title = $modal.find('.ratnotes-frontend-note-title');
            const $content = $modal.find('.ratnotes-frontend-note-content');

            // Reset modal
            $title.val('');
            $content.val('');
            $modal.find('.ratnotes-frontend-color-btn').removeClass('active');

            if (this.currentNote) {
                $title.val(this.currentNote.title);
                $content.val(this.currentNote.content);
                $modal.find(`.ratnotes-frontend-color-btn[data-color="${this.currentNote.color}"]`).addClass('active');
            } else {
                // Default color for new notes
                $modal.find('.ratnotes-frontend-color-btn[data-color="#ffffff"]').addClass('active');
            }

            $modal.fadeIn(200);
            $title.focus();
        },

        /**
         * Close modal.
         */
        closeModal: function() {
            this.$container.find('.ratnotes-frontend-modal').fadeOut(200);
            this.currentNote = null;
        },

        /**
         * Save note.
         */
        saveNote: async function() {
            const $modal = this.$container.find('.ratnotes-frontend-modal');
            const title = $modal.find('.ratnotes-frontend-note-title').val().trim();
            const content = $modal.find('.ratnotes-frontend-note-content').val().trim();
            const color = $modal.find('.ratnotes-frontend-color-btn.active').data('color') || '#ffffff';

            if (!title && !content) {
                this.closeModal();
                return;
            }

            try {
                const response = await $.ajax({
                    url: ratnotesFrontendData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ratnotes_save_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote ? this.currentNote.id : 0,
                        title: title,
                        content: content,
                        color: color,
                        is_pinned: this.currentNote ? this.currentNote.is_pinned : false,
                        is_archived: this.currentNote ? this.currentNote.is_archived : false
                    }
                });

                if (response.success) {
                    this.closeModal();
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to save note');
                }
            } catch (error) {
                console.error('Error saving note:', error);
                this.showError('Failed to save note');
            }
        },

        /**
         * Delete note.
         */
        deleteNote: async function() {
            if (!this.currentNote) return;

            if (!confirm(ratnotesFrontendData.strings.confirmDelete)) return;

            try {
                const response = await $.ajax({
                    url: ratnotesFrontendData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ratnotes_delete_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        force: this.currentStatus === 'trash'
                    }
                });

                if (response.success) {
                    this.closeModal();
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to delete note');
                }
            } catch (error) {
                console.error('Error deleting note:', error);
                this.showError('Failed to delete note');
            }
        },

        /**
         * Archive note.
         */
        archiveNote: async function() {
            if (!this.currentNote) return;

            try {
                const isArchived = this.currentNote.is_archived || false;

                const response = await $.ajax({
                    url: ratnotesFrontendData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ratnotes_save_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        title: this.currentNote.title,
                        content: this.currentNote.content,
                        color: this.currentNote.color,
                        is_pinned: this.currentNote.is_pinned,
                        is_archived: !isArchived
                    }
                });

                if (response.success) {
                    this.closeModal();
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to archive note');
                }
            } catch (error) {
                console.error('Error archiving note:', error);
                this.showError('Failed to archive note');
            }
        },

        /**
         * Toggle pin status.
         */
        togglePin: async function() {
            if (!this.currentNote) return;

            try {
                const response = await $.ajax({
                    url: ratnotesFrontendData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ratnotes_save_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        title: this.currentNote.title,
                        content: this.currentNote.content,
                        color: this.currentNote.color,
                        is_pinned: !this.currentNote.is_pinned,
                        is_archived: this.currentNote.is_archived
                    }
                });

                if (response.success) {
                    this.currentNote.is_pinned = !this.currentNote.is_pinned;
                    this.closeModal();
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to update pin status');
                }
            } catch (error) {
                console.error('Error toggling pin:', error);
                this.showError('Failed to update pin status');
            }
        },

        /**
         * Select color.
         */
        selectColor: function(e) {
            this.$container.find('.ratnotes-frontend-color-btn').removeClass('active');
            $(e.target).addClass('active');
        },

        /**
         * Handle navigation click.
         */
        handleNavClick: function(e) {
            this.$container.find('.ratnotes-frontend-nav-item').removeClass('active');
            $(e.currentTarget).addClass('active');
            this.currentStatus = $(e.currentTarget).data('status');
            this.loadNotes();
        },

        /**
         * Handle search.
         */
        handleSearch: function(e) {
            const query = e.target.value.trim();
            const filtered = this.notes.filter(note =>
                (note.title && note.title.toLowerCase().includes(query.toLowerCase())) ||
                (note.content && note.content.toLowerCase().includes(query.toLowerCase()))
            );
            this.renderNotes(filtered);
        },

        /**
         * Handle keyboard shortcuts.
         */
        handleKeyboard: function(e) {
            // Escape to close modal
            if (e.key === 'Escape' && this.$container.find('.ratnotes-frontend-modal').is(':visible')) {
                this.closeModal();
            }
        },

        /**
         * Show error message.
         */
        showError: function(message) {
            alert(message);
        },

        /**
         * Escape HTML.
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready.
    $(document).ready(() => {
        RatNotesFrontend.init();
    });

})(jQuery);
