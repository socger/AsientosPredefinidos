<?php
namespace FacturaScripts\Plugins\AsientosPredefinidos\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class EditAsientoPredefinido extends \FacturaScripts\Core\Lib\ExtendedController\EditController
{
    public function getModelClassName() {
        return "AsientoPredefinido";
    }

    public function getPageData() {
        $pageData = parent::getPageData();
        $pageData["title"] = "Asiento Predefinido";
        $pageData["icon"] = "fas fa-cogs";
        return $pageData;
    }
    
    protected function createViews() {
        parent::createViews();
        
        $this->createViewEditAsientoPredefinidoLinea();
        
        $this->setTabsPosition('bottom'); // Las posiciones de las pestañas pueden ser left, top, down
    }
    
    protected function createViewEditAsientoPredefinidoLinea(string $viewName = 'EditAsientoPredefinidoLinea')
    {
        $this->addEditListView($viewName, 'AsientoPredefinidoLinea', 'Asientos predefinidos - Líneas');
        $this->views[$viewName]->setInLine(true);

        /// disable columns
        $this->views[$viewName]->disableColumn('idasientopredefinido');
        $this->views[$viewName]->disableColumn('idasientopredefinidolinea');
    }
    
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditAsientoPredefinidoLinea':
                $idasientopredefinido = $this->getViewModelValue($this->getMainViewName(), 'idasientopredefinido');
                $where = [new DataBaseWhere('idasientopredefinido', $idasientopredefinido)];
                $view->loadData('', $where, ['idasientopredefinido' => 'ASC', 'orden' => 'ASC']);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
    
}
