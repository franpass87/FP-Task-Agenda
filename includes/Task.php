<?php
/**
 * Modello Task
 * 
 * Classe helper per lavorare con i task
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class Task {
    
    /**
     * Ottiene le priorità disponibili
     */
    public static function get_priorities() {
        return array(
            'low' => __('Bassa', 'fp-task-agenda'),
            'normal' => __('Normale', 'fp-task-agenda'),
            'high' => __('Alta', 'fp-task-agenda'),
            'urgent' => __('Urgente', 'fp-task-agenda')
        );
    }
    
    /**
     * Ottiene gli stati disponibili
     */
    public static function get_statuses() {
        return array(
            'pending' => __('Da fare', 'fp-task-agenda'),
            'in_progress' => __('In corso', 'fp-task-agenda'),
            'completed' => __('Completato', 'fp-task-agenda')
        );
    }
    
    /**
     * Ottiene la classe CSS per la priorità
     */
    public static function get_priority_class($priority) {
        $classes = array(
            'low' => 'priority-low',
            'normal' => 'priority-normal',
            'high' => 'priority-high',
            'urgent' => 'priority-urgent'
        );
        
        return isset($classes[$priority]) ? $classes[$priority] : 'priority-normal';
    }
    
    /**
     * Ottiene l'icona per la priorità
     */
    public static function get_priority_icon($priority) {
        $icons = array(
            'low' => 'dashicons-arrow-down-alt',
            'normal' => 'dashicons-minus',
            'high' => 'dashicons-arrow-up-alt',
            'urgent' => 'dashicons-warning'
        );
        
        return isset($icons[$priority]) ? $icons[$priority] : 'dashicons-minus';
    }
    
    /**
     * Formatta la data di scadenza
     */
    public static function format_due_date($date_string) {
        if (empty($date_string)) {
            return '';
        }
        
        $timestamp = strtotime($date_string);
        if (!$timestamp) {
            return '';
        }
        
        $now = current_time('timestamp');
        $diff = $timestamp - $now;
        $days = floor($diff / DAY_IN_SECONDS);
        
        if ($days < 0) {
            return sprintf(__('Scaduto %d giorni fa', 'fp-task-agenda'), abs($days));
        } elseif ($days == 0) {
            return __('Scade oggi', 'fp-task-agenda');
        } elseif ($days == 1) {
            return __('Scade domani', 'fp-task-agenda');
        } else {
            return sprintf(__('Scade tra %d giorni', 'fp-task-agenda'), $days);
        }
    }
    
    /**
     * Verifica se un task è in scadenza (entro 3 giorni)
     */
    public static function is_due_soon($date_string) {
        if (empty($date_string)) {
            return false;
        }
        
        $timestamp = strtotime($date_string);
        if (!$timestamp) {
            return false;
        }
        
        $now = current_time('timestamp');
        $diff = $timestamp - $now;
        $days = floor($diff / DAY_IN_SECONDS);
        
        return $days >= 0 && $days <= 3;
    }
}
