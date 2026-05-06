<?php
/**
 * MedicalOT - Helper Functions
 * Este archivo es requerido por composer.json para la carga automática.
 * Aquí puedes agregar funciones globales utilitarias.
 */

// Función de ayuda segura (evita error "Cannot redeclare" si ya existe en otro lado)
if (!function_exists('updateDateTime')) {
    function updateDateTime() {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('America/Santiago'));
        return $date->format('l, d \d\e F \d\e Y - H:i');
    }
}