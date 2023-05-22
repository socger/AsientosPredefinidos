<?php
/**
 * This file is part of AsientoPredefinido plugin for FacturaScripts
 * Copyright (C) 2021-2022 Carlos Garcia Gomez            <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\AsientosPredefinidos\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Plugins\AsientosPredefinidos\Lib\AsientoPredefinidoGenerator;

class AsientoPredefinido extends ModelClass
{

    use ModelTrait;

    /**
     * @var string
     */
    public $concepto;

    /**
     * @var string
     */
    public $descripcion;

    /**
     * @var int
     */
    public $id;

    public function generate(array $form): Asiento
    {
        return AsientoPredefinidoGenerator::generate($this, $form);
    }

    /**
     * Devuelve un array con las líneas del asiento predefinido.
     *
     * @return AsientoPredefinidoLinea[]
     */
    public function getLines(): array
    {
        $line = new AsientoPredefinidoLinea();
        $where = [new DataBaseWhere("idasientopre", $this->id)];
        return $line->all($where);
    }

    /**
     * Devuelve un array con las variables del asiento predefinido.
     *
     * @return AsientoPredefinidoVariable[]
     */
    public function getVariables(): array
    {
        $variable = new AsientoPredefinidoVariable();
        $where = [new DataBaseWhere("idasientopre", $this->id)];
        return $variable->all($where);
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "asientospre";
    }

    public function test(): bool
    {
        $utils = $this->toolBox()->utils();
        $this->concepto = $utils->noHtml($this->concepto);
        $this->descripcion = $utils->noHtml($this->descripcion);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListAsiento?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
