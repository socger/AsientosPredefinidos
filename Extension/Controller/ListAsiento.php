<?php
namespace FacturaScripts\Plugins\AsientosPredefinidos\Extension\Controller;

//use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListAsiento /*extends ListController*/
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
            

            // $this->addListView('List' . $model, $model, 'Asientos predefinidos', 'fas fa-cogs');    

    /*        
            $this->addOrderBy($viewName, ["idasientopredefinido"], "Id");
            $this->addOrderBy($viewName, ["descripcion"], "Descripción", 1);

            // Esto es un ejemplo ... debe de cambiarlo según los nombres de campos del modelo
            $this->addSearchFields($viewName, ["idasientopredefinido", "descripcion"]);
     * 
     */
        };
    }
    

/*


    
    // execAfterAction() se ejecuta tras el execAfterAction() del controlador.
    public function execAfterAction() {
       return function($action) {
          /// tu código aquí
       };
    }

    // execPreviousAction() se ejecuta después del execPreviousAction() del controlador. Si devolvemos false detenemos la ejecución del controlador.
    public function execPreviousAction() {
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
