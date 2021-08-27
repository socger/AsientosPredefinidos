<?php

namespace FacturaScripts\Plugins\AsientosPredefinidos\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class EditAsientoPredefinido extends \FacturaScripts\Core\Lib\ExtendedController\EditController {

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

    protected function createViewEditAsientoPredefinidoLinea(string $viewName = 'EditAsientoPredefinidoLinea') {
        $this->addEditListView($viewName, 'AsientoPredefinidoLinea', 'Asientos predefinidos - Líneas');
        $this->views[$viewName]->setInLine(true);
    }

    protected function loadData($viewName, $view) {
        switch ($viewName) {
            case 'EditAsientoPredefinidoLinea':
                $idasientopre = $this->getViewModelValue($this->getMainViewName(), 'id');
                $where = [new DataBaseWhere('idasientopre', $idasientopre)];
                $view->loadData('', $where, ['orden' => 'ASC', 'idasientopre' => 'ASC']);
                break;

            default:
                parent::loadData($viewName, $view);

                // Sólo si el registro existe se añade el botón
                if ($view->model->exists()) {
                    $this->addButton($viewName, [
                        "action" => "gen-accounting",
                        "color" => "success",
                        "label" => "generate-accounting-entry",
                        "icon" => "fas fa-magic",
                        "type" => "modal"
                    ]);
                }

                break;
        }
    }

    protected function execAfterAction($action) {
        if ($action === 'gen-accounting') {
            $this->generateAccountingAction();
            return;
        }

        return parent::execAfterAction($action);
    }

    protected function generateAccountingAction() {
        $date = (string) $this->request->request->get('date', '');
        $idempresa = (int) $this->request->request->get('idempresa');

        $asiento = $this->getModel()->generate($date, $idempresa);
        if ($asiento->exists()) {
            $this->toolBox()->i18nLog()->info("Generado correctamente el asiento");
            $this->redirect($asiento->url(), 1); // El parámetro 1 es un temporizador en redireccionar, así el usuario ve el mensaje de la línea anterior
        } else {
            $this->toolBox()->i18nLog()->warning('No se pudo generar el asiento.');
        }
    }

}
