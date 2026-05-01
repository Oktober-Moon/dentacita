<?php
/*
 * INVENTARIO · catálogos cerrados
 * --------------------------------
 * Listas fijas reutilizadas por el modal del producto, las
 * validaciones server-side y los renders de la tabla.
 *
 * Las claves se persisten en la BD (inventario_items.categoria,
 * inventario_items.unidad). Los labels son los que se muestran
 * al usuario.
 */

const INV_CATEGORIAS = [
    'consumibles'  => 'Consumibles',
    'instrumentos' => 'Instrumentos',
    'materiales'   => 'Materiales',
    'equipamiento' => 'Equipamiento',
    'medicamentos' => 'Medicamentos',
    'general'      => 'General',
];

const INV_UNIDADES = [
    'unidad'  => 'Unidad',
    'caja'    => 'Caja',
    'paquete' => 'Paquete',
    'ml'      => 'Mililitros',
    'gr'      => 'Gramos',
    'par'     => 'Par',
];

const INV_CATEGORIA_DEFAULT = 'general';
const INV_UNIDAD_DEFAULT    = 'unidad';

function invCategoriaLabel($value) {
    return INV_CATEGORIAS[$value] ?? INV_CATEGORIAS[INV_CATEGORIA_DEFAULT];
}

function invUnidadLabel($value) {
    return INV_UNIDADES[$value] ?? INV_UNIDADES[INV_UNIDAD_DEFAULT];
}
