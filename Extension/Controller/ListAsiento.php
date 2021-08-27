<?php

namespace FacturaScripts\Plugins\AsientosPredefinidos\Extension\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class ListAsiento {

    // createViews() se ejecuta una vez realiado el createViews() del controlador.
    public function createViews() {
        return function () {
            $this->createAsientosPredefinidos();
        };
    }

    protected function createAsientosPredefinidos() {
        return function (string $viewName = 'ListAsientoPredefinido') {
            $this->addView($viewName, 'AsientoPredefinido', 'predefined-accounting-entry', 'fas fa-cogs');

            // Esto es un ejemplo ... debe de cambiarlo según los nombres de campos del modelo
            $this->addOrderBy($viewName, ["id"], "code");
            $this->addOrderBy($viewName, ["descripcion"], "description", 1);

            // Esto es un ejemplo ... debe de cambiarlo según los nombres de campos del modelo
            $this->addSearchFields($viewName, ["id", "descripcion"]);
        };
    }

}
