/**
 * RatNotes Admin JavaScript
 *
 * @package RatNotes
 */

(function($) {
    'use strict';

    /**
     * Main application object.
     */
    const RatNotes = {
        notes: [],
        currentNote: null,
        currentStatus: 'active',
        isLoading: false,

        /**
         * Initialize the application.
         */
        init: function() {
            this.bindEvents();
            this.loadNotes();
        },

        /**
         * Bind event listeners.
         */
        bindEvents: function() {
            // Navigation
            $('.ratnotes-nav-item').on('click', (e) => this.handleNavClick(e));
            
            // Search
            $('#ratnotes-search').on('input', (e) => this.handleSearch(e));
            
            // Create button
            $('#ratnotes-create-btn').on('click', () => this.openModal());
            
            // Modal close
            $('#ratnotes-modal-close, .ratnotes-modal-overlay').on('click', () => this.closeModal());
            
            // Save button
            $('#ratnotes-save-btn').on('click', () => this.saveNote());
            
            // Delete button
            $('#ratnotes-delete-btn').on('click', () => this.deleteNote());
            
            // Archive button
            $('#ratnotes-archive-btn').on('click', () => this.archiveNote());
            
            // Pin button
            $('#ratnotes-pin-btn').on('click', () => this.togglePin());
            
            // Color picker
            $('.ratnotes-color-btn').on('click', (e) => this.selectColor(e));
            
            // Keyboard shortcuts
            $(document).on('keydown', (e) => this.handleKeyboard(e));
        },

        /**
         * Load notes from API.
         */
        loadNotes: async function() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            $('#ratnotes-loading').addClass('is-active');
            
            try {
                const response = await fetch(
                    `${ratnotesData.root}ratnotes/v1/notes?status=${this.currentStatus}`,
                    {
                        headers: {
                            'X-WP-Nonce': ratnotesData.nonce
                        }
                    }
                );
                
                if (!response.ok) throw new Error('Failed to load notes');
                
                this.notes = await response.json();
                this.renderNotes();
            } catch (error) {
                console.error('Error loading notes:', error);
                this.showError('Failed to load notes');
            } finally {
                this.isLoading = false;
                $('#ratnotes-loading').removeClass('is-active');
            }
        },

        /**
         * Render notes grid.
         */
        renderNotes: function() {
            const $grid = $('#ratnotes-notes-grid');
            
            if (this.notes.length === 0) {
                $grid.html(this.renderEmptyState());
                return;
            }
            
            // Sort: pinned first
            const sortedNotes = [...this.notes].sort((a, b) => {
                if (a.is_pinned && !b.is_pinned) return -1;
                if (!a.is_pinned && b.is_pinned) return 1;
                return new Date(b.created_at) - new Date(a.created_at);
            });
            
            $grid.html(
                sortedNotes.map(note => this.renderNoteCard(note)).join('')
            );
            
            // Bind card click events
            $('.ratnotes-note-card').on('click', (e) => {
                const noteId = $(e.currentTarget).data('id');
                this.openModal(noteId);
            });
        },

        /**
         * Render a single note card.
         */
        renderNoteCard: function(note) {
            const pinnedClass = note.is_pinned ? 'pinned' : '';
            const labels = note.labels.map(label => 
                `<span class="ratnotes-label">${this.escapeHtml(label)}</span>`
            ).join('');
            
            return `
                <div class="ratnotes-note-card ${pinnedClass}" 
                     data-id="${note.id}" 
                     style="background-color: ${note.color}">
                    ${note.title ? `<div class="ratnotes-note-title">${this.escapeHtml(note.title)}</div>` : ''}
                    <div class="ratnotes-note-content">${this.escapeHtml(note.content)}</div>
                    ${note.labels.length > 0 ? `<div class="ratnotes-note-labels">${labels}</div>` : ''}
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
                    title: ratnotesData.strings.createNote,
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
                    text: 'Deleted notes will appear here for 30 days'
                }
            };
            
            const msg = messages[this.currentStatus];
            
            return `
                <div class="ratnotes-empty">
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
            this.currentNote = noteId ? this.notes.find(n => n.id === noteId) : null;
            
            // Reset modal
            $('#ratnotes-note-title').val('');
            $('#ratnotes-note-content').val('');
            $('.ratnotes-color-btn').removeClass('active');
            
            if (this.currentNote) {
                $('#ratnotes-note-title').val(this.currentNote.title);
                $('#ratnotes-note-content').val(this.currentNote.content);
                $(`.ratnotes-color-btn[data-color="${this.currentNote.color}"]`).addClass('active');
            }
            
            $('#ratnotes-modal').fadeIn(200);
            $('#ratnotes-note-title').focus();
        },

        /**
         * Close modal.
         */
        closeModal: function() {
            $('#ratnotes-modal').fadeOut(200);
            this.currentNote = null;
        },

        /**
         * Save note.
         */
        saveNote: async function() {
            const title = $('#ratnotes-note-title').val().trim();
            const content = $('#ratnotes-note-content').val().trim();
            const color = $('.ratnotes-color-btn.active').data('color') || '#ffffff';
            
            if (!title && !content) {
                this.closeModal();
                return;
            }
            
            try {
                const url = this.currentNote 
                    ? `${ratnotesData.root}ratnotes/v1/notes/${this.currentNote.id}`
                    : `${ratnotesData.root}ratnotes/v1/notes`;
                
                const method = this.currentNote ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'X-WP-Nonce': ratnotesData.nonce,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: title,
                        content: content,
                        color: color
                    })
                });
                
                if (!response.ok) throw new Error('Failed to save note');
                
                this.closeModal();
                this.loadNotes();
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
            
            if (!confirm(ratnotesData.strings.confirmDelete)) return;
            
            try {
                const force = this.currentStatus === 'trash';
                
                const response = await fetch(
                    `${ratnotesData.root}ratnotes/v1/notes/${this.currentNote.id}?force=${force}`,
                    {
                        method: 'DELETE',
                        headers: {
                            'X-WP-Nonce': ratnotesData.nonce
                        }
                    }
                );
                
                if (!response.ok) throw new Error('Failed to delete note');
                
                this.closeModal();
                this.loadNotes();
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
                const response = await fetch(
                    `${ratnotesData.root}ratnotes/v1/notes/${this.currentNote.id}`,
                    {
                        method: 'PUT',
                        headers: {
                            'X-WP-Nonce': ratnotesData.nonce,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            is_archived: this.currentStatus === 'archived' ? 0 : 1
                        })
                    }
                );
                
                if (!response.ok) throw new Error('Failed to archive note');
                
                this.closeModal();
                this.loadNotes();
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
                const response = await fetch(
                    `${ratnotesData.root}ratnotes/v1/notes/${this.currentNote.id}`,
                    {
                        method: 'PUT',
                        headers: {
                            'X-WP-Nonce': ratnotesData.nonce,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            is_pinned: !this.currentNote.is_pinned
                        })
                    }
                );
                
                if (!response.ok) throw new Error('Failed to update pin status');
                
                this.currentNote.is_pinned = !this.currentNote.is_pinned;
                this.loadNotes();
            } catch (error) {
                console.error('Error toggling pin:', error);
                this.showError('Failed to update pin status');
            }
        },

        /**
         * Select color.
         */
        selectColor: function(e) {
            $('.ratnotes-color-btn').removeClass('active');
            $(e.target).addClass('active');
        },

        /**
         * Handle navigation click.
         */
        handleNavClick: function(e) {
            $('.ratnotes-nav-item').removeClass('active');
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
                note.title.toLowerCase().includes(query.toLowerCase()) ||
                note.content.toLowerCase().includes(query.toLowerCase())
            );
            this.renderNotes();
        },

        /**
         * Handle keyboard shortcuts.
         */
        handleKeyboard: function(e) {
            // Escape to close modal
            if (e.key === 'Escape' && $('#ratnotes-modal').is(':visible')) {
                this.closeModal();
            }
            
            // Ctrl/Cmd + N to create new note
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                this.openModal();
            }
        },

        /**
         * Show error message.
         */
        showError: function(message) {
            // Could implement a proper notification system here
            console.error(message);
        },

        /**
         * Escape HTML.
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready.
    $(document).ready(() => {
        RatNotes.init();
    });

})(jQuery);
