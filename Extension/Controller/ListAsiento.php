<?php
namespace FacturaScripts\Plugins\AsientosPredefinidos\Extension\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class ListAsiento
{
    // createViews() se ejecuta una vez realiado el createViews() del controlador.
    public function createViews() {
        return function() {
            $this->createAsientosPredefinidos();
        };
    }

    protected function createAsientosPredefinidos()
    {
        return function(string $model = 'AsientoPredefinido') {
            $viewName = 'List' . $model;
            
            $this->addView($viewName, $model, 'Asientos predefinidos', 'fas fa-cogs');

            // Esto es un ejemplo ... debe de cambiarlo según los nombres de campos del modelo
            $this->addOrderBy($viewName, ["idasientopredefinido"], "code");
            $this->addOrderBy($viewName, ["descripcion"], "description", 1);

            // Esto es un ejemplo ... debe de cambiarlo según los nombres de campos del modelo
            $this->addSearchFields($viewName, ["idasientopredefinido", "descripcion"]);

            /// Filters
            $this->addFilterSelectWhere($viewName, 'status', [
                ['label' => $this->toolBox()->i18n()->trans('only-active'), 'where' => [new DataBaseWhere('debaja', false)]],
                ['label' => $this->toolBox()->i18n()->trans('only-suspended'), 'where' => [new DataBaseWhere('debaja', true)]],
                ['label' => $this->toolBox()->i18n()->trans('all'), 'where' => []]
            ]);

            $this->addButton($viewName, [
                'action' => 'gen-accounting',
                'icon' => 'fas fa-magic',
                'label' => 'generate-accounting-entry',
                'type' => 'modal'
            ]);

        };
    }
    
    // execPreviousAction() se ejecuta después del execPreviousAction() del controlador. Si devolvemos false detenemos la ejecución del controlador.
    public function execPreviousAction() {
        return function($action) {
            if ($action === 'gen-accounting') {
                $this->generateAccounting();
            }

        };
    }

    protected function generateAccounting()
    {
        return function() {
            $date = $this->request->request->get('date', '');
            $idempresa = $this->request->request->get('idempresa');
            $codejercicio = $this->request->request->get('codejercicio');
            $idasientopredefinido = $this->request->request->get('idasientopredefinido');

            $this->toolBox()->i18nLog()->info('Hay que generar el asiento .... TODAVIA SIN HACER');
        };
    }


/*


    
    // execAfterAction() se ejecuta tras el execAfterAction() del controlador.
    public function execAfterAction() {
       return function($action) {
          /// tu código aquí
       };
    }

    // loadData() se ejecuta tras el loadData() del controlador. Recibe los parámetros $viewName y $view.
    public function loadData() {
       return function($viewName, $view) {
          /// tu código aquí
       };
    }
*/
    
}
