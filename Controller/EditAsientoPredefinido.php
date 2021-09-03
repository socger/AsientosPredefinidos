<?php
/**
 * This file is part of AsientoPredefinido plugin for FacturaScripts
 * Copyright (C) 2021 Carlos Garcia Gomez            <carlos@facturascripts.com>
 *                    Jeronimo Pedro Sánchez Manzano <socger@gmail.com>
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\AsientosPredefinidos\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditAsientoPredefinido extends EditController
{

    public function getModelClassName()
    {
        return "AsientoPredefinido";
    }

    public function getPageData()
    {
        $pageData = parent::getPageData();
        $pageData["title"] = "predefined-acc-entry";
        $pageData["menu"] = "accounting";
        $pageData["icon"] = "fas fa-cogs";
        return $pageData;
    }

    protected function createViews()
    {
        parent::createViews();
        
        $this->createViewsInfo();
        $this->createViewsLineas();
        $this->createViewsVariables();
        
        $this->setTabsPosition('bottom'); // Las posiciones de las pestañas pueden ser left, top, bottom
    }
    
    protected function createViewsInfo(string $viewName = 'Info') {
        $this->addHtmlView($viewName, 'AsientoPredefinidoInfo', 'AsientoPredefinido', 'help', 'fas fa-question-circle');
    }

    protected function createViewsLineas(string $viewName = 'EditAsientoPredefinidoLinea')
    {
        $this->addEditListView($viewName, 'AsientoPredefinidoLinea', 'lines');
        $this->views[$viewName]->setInLine(true);
    }

    protected function createViewsVariables(string $viewName = 'EditAsientoPredefinidoVariable')
    {
        $this->addEditListView($viewName, 'AsientoPredefinidoVariable', 'Variables');
        $this->views[$viewName]->setInLine(true);
    }
    
    protected function execAfterAction($action)
    {
        if ($action === 'gen-accounting') {
            $this->generateAccountingAction();
            return;
        }

        parent::execAfterAction($action);
    }

    protected function generateAccountingAction()
    {
        $date = (string)$this->request->request->get('date', self::toolBox()::today());
        $idempresa = (int)$this->request->request->get('idempresa');
        if (empty($date) || empty($idempresa)) {
            return;
        }

        $asiento = $this->getModel()->generate($date, $idempresa);
        if ($asiento->exists()) {
            $this->toolBox()->i18nLog()->info('generated-accounting-entries', ['%quantity%' => 1]);
            $this->redirect($asiento->url(), 1); // El parámetro 1 es un temporizador en redireccionar, así el usuario ve el mensaje de la línea anterior
            return;
        }

        $this->toolBox()->i18nLog()->warning('record-save-error');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditAsientoPredefinidoLinea':
                $idasientopre = $this->getViewModelValue($this->getMainViewName(), 'id');
                $where = [new DataBaseWhere('idasientopre', $idasientopre)];
                $view->loadData('', $where, ['orden' => 'ASC', 'idasientopre' => 'ASC']);
                break;
            
            case 'EditAsientoPredefinidoVariable':
                $idasientopre = $this->getViewModelValue($this->getMainViewName(), 'id');
                $where = [new DataBaseWhere('idasientopre', $idasientopre)];
                $view->loadData('', $where, ['idasientopre' => 'ASC', 'codigo' => 'ASC']);
                
                break;
            
            default:
                parent::loadData($viewName, $view);

                // Sólo si el registro existe se añade el botón
                if ($view->model->exists()) {
                    $this->addButton($viewName, [
                        "action" => "gen-accounting",
                        "color" => "success",
                        "label" => "generate",
                        "icon" => "fas fa-magic",
                        "type" => "modal"
                    ]);
                }
                break;
        }
    }
}
