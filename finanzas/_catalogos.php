<?php
/*
 * FINANZAS · catálogos cerrados de categorías
 * --------------------------------------------
 * Categorías fijas para transacciones, equivalentes a las del
 * proyecto grande (Pro-Connect-Hub):
 *   - Ingresos: dentistry.incomeCategories
 *   - Egresos:  expenseCategories
 *
 * El value persistido coincide con el label (VARCHAR 50).
 *
 * 'Reembolso' es una categoría especial: NO aparece en los
 * dropdowns, pero se acepta cuando la inserción viene del flujo
 * de reembolsos (finanzas/reembolso.php).
 */

const FIN_CATEGORIAS_INGRESO = [
    'Consulta',
    'Tratamiento',
    'Procedimiento',
    'Producto',
    'Otro',
];

const FIN_CATEGORIAS_EGRESO = [
    'Materiales',
    'Equipo',
    'Alquiler',
    'Servicios',
    'Marketing',
    'Software',
    'Transporte',
    'Otro',
];

const FIN_CATEGORIA_REEMBOLSO = 'Reembolso';

function finCategoriasPorTipo($tipo) {
    if ($tipo === 'ingreso') return FIN_CATEGORIAS_INGRESO;
    if ($tipo === 'egreso')  return FIN_CATEGORIAS_EGRESO;
    return [];
}

function finCategoriasUnion() {
    $u = array_unique(array_merge(FIN_CATEGORIAS_INGRESO, FIN_CATEGORIAS_EGRESO));
    sort($u, SORT_NATURAL | SORT_FLAG_CASE);
    return $u;
}
