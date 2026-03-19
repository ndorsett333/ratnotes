<?php
/**
 * Admin page template.
 *
 * @package RatNotes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap ratnotes-admin">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div id="ratnotes-app" class="ratnotes-app">
        <div class="ratnotes-sidebar">
            <nav class="ratnotes-nav">
                <button class="ratnotes-nav-item active" data-status="active">
                    <span class="dashicons dashicons-admin-notes"></span>
                    <?php esc_html_e( 'Notes', 'ratnotes' ); ?>
                </button>
                <button class="ratnotes-nav-item" data-status="archived">
                    <span class="dashicons dashicons-archive"></span>
                    <?php esc_html_e( 'Archive', 'ratnotes' ); ?>
                </button>
                <button class="ratnotes-nav-item" data-status="trash">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'Trash', 'ratnotes' ); ?>
                </button>
            </nav>
        </div>
        
        <div class="ratnotes-main">
            <div class="ratnotes-header">
                <div class="ratnotes-search">
                    <input 
                        type="search" 
                        id="ratnotes-search" 
                        class="ratnotes-search-input" 
                        placeholder="<?php esc_attr_e( 'Search notes...', 'ratnotes' ); ?>"
                    />
                </div>
                <button id="ratnotes-create-btn" class="button button-primary">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e( 'New Note', 'ratnotes' ); ?>
                </button>
            </div>
            
            <div id="ratnotes-notes-grid" class="ratnotes-notes-grid">
                <!-- Notes will be loaded here -->
            </div>
            
            <div id="ratnotes-loading" class="ratnotes-loading">
                <span class="spinner is-active"></span>
            </div>
        </div>
    </div>
    
    <!-- Note Editor Modal -->
    <div id="ratnotes-modal" class="ratnotes-modal" style="display: none;">
        <div class="ratnotes-modal-overlay"></div>
        <div class="ratnotes-modal-content">
            <div class="ratnotes-modal-header">
                <input 
                    type="text" 
                    id="ratnotes-note-title" 
                    class="ratnotes-note-title" 
                    placeholder="<?php esc_attr_e( 'Title', 'ratnotes' ); ?>"
                />
                <button id="ratnotes-modal-close" class="ratnotes-modal-close">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="ratnotes-modal-body">
                <textarea 
                    id="ratnotes-note-content" 
                    class="ratnotes-note-content" 
                    placeholder="<?php esc_attr_e( 'Take a note...', 'ratnotes' ); ?>"
                ></textarea>
            </div>
            <div class="ratnotes-modal-footer">
                <div class="ratnotes-color-picker">
                    <button class="ratnotes-color-btn" data-color="#ffffff" style="background-color: #ffffff;"></button>
                    <button class="ratnotes-color-btn" data-color="#f28b82" style="background-color: #f28b82;"></button>
                    <button class="ratnotes-color-btn" data-color="#fbbc04" style="background-color: #fbbc04;"></button>
                    <button class="ratnotes-color-btn" data-color="#fff475" style="background-color: #fff475;"></button>
                    <button class="ratnotes-color-btn" data-color="#ccff90" style="background-color: #ccff90;"></button>
                    <button class="ratnotes-color-btn" data-color="#a7ffeb" style="background-color: #a7ffeb;"></button>
                    <button class="ratnotes-color-btn" data-color="#cbf0f8" style="background-color: #cbf0f8;"></button>
                    <button class="ratnotes-color-btn" data-color="#d0c4ff" style="background-color: #d0c4ff;"></button>
                    <button class="ratnotes-color-btn" data-color="#ffccbc" style="background-color: #ffccbc;"></button>
                </div>
                <div class="ratnotes-actions">
                    <button id="ratnotes-pin-btn" class="button">
                        <span class="dashicons dashicons-pin"></span>
                    </button>
                    <button id="ratnotes-archive-btn" class="button">
                        <span class="dashicons dashicons-archive"></span>
                    </button>
                    <button id="ratnotes-delete-btn" class="button">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    <button id="ratnotes-save-btn" class="button button-primary">
                        <?php esc_html_e( 'Close', 'ratnotes' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
