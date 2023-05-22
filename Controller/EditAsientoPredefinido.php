<?php
/**
 * This file is part of AsientoPredefinido plugin for FacturaScripts
 * Copyright (C) 2021-2023 Carlos Garcia Gomez            <carlos@facturascripts.com>
 *                         Jeronimo Pedro Sánchez Manzano <socger@gmail.com>
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
    public function getModelClassName(): string
    {
        return "AsientoPredefinido";
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["menu"] = "accounting";
        $pageData["title"] = "predefined-acc-entry";
        $pageData["icon"] = "fas fa-blender";
        return $pageData;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewsInfo();
        $this->createViewsGenerar();
        $this->createViewsLineas();
        $this->createViewsVariables();
    }

    protected function createViewsGenerar(string $viewName = 'Generar'): void
    {
        $this->addHtmlView($viewName, 'AsientoPredefinidoGenerar', 'AsientoPredefinido', 'generate', 'fas fa-magic');
    }

    protected function createViewsInfo(string $viewName = 'Info'): void
    {
        $this->addHtmlView($viewName, 'AsientoPredefinidoInfo', 'AsientoPredefinido', 'help', 'fas fa-info-circle');
    }

    protected function createViewsLineas(string $viewName = 'EditAsientoPredefinidoLinea'): void
    {
        $this->addEditListView($viewName, 'AsientoPredefinidoLinea', 'lines');
        $this->views[$viewName]->setInLine(true);
    }

    protected function createViewsVariables(string $viewName = 'EditAsientoPredefinidoVariable'): void
    {
        $this->addEditListView($viewName, 'AsientoPredefinidoVariable', 'variables', 'fas fa-tools');
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

    protected function generateAccountingAction(): void
    {
        $form = $this->request->request->all(); // Nos traemos todos los campos del form de la vista AsientoPredefinidoGenerar.html.twig
        if (empty($form["fecha"]) || empty($form["idempresa"])) {
            $this->toolBox()->i18nLog()->warning('No ha introducido ni la empresa, ni la fecha');
            return;
        }

        // Llamamos al método generate() del modelo AsientoPredefinido y le pasamos el form
        $asiento = $this->getModel()->generate($form);
        if ($asiento->exists()) {
            // Se ha creado el siento, así que sacamos mensaje, esperamos un segundo y saltamos a la dirección del asiento recién creado.
            $this->toolBox()->i18nLog()->notice('generated-accounting-entries', ['%quantity%' => 1]);
            $this->redirect($asiento->url() . "&action=save-ok", 1);
            // ."&action=save-ok" es para que saque un mensaje de que registro creado ok y el parámetro 1
            // es un temporizador en redireccionar, así el usuario ve el mensaje de la línea anterior
            return;
        }

        $this->toolBox()->i18nLog()->warning('record-save-error');
    }

    protected function loadData($viewName, $view)
    {
        $id = $this->getViewModelValue($this->getMainViewName(), 'id');

        switch ($viewName) {
            case 'EditAsientoPredefinidoLinea':
                $where = [new DataBaseWhere('idasientopre', $id)];
                $view->loadData('', $where, ['orden' => 'ASC', 'idasientopre' => 'ASC']);
                break;

            case 'EditAsientoPredefinidoVariable':
                $where = [new DataBaseWhere('idasientopre', $id)];
                $view->loadData('', $where, ['idasientopre' => 'ASC', 'codigo' => 'ASC']);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
