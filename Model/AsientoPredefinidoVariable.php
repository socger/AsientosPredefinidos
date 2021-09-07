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

namespace FacturaScripts\Plugins\AsientosPredefinidos\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class AsientoPredefinidoVariable extends ModelClass
{
    use ModelTrait;

    /**
     * @var string
     */
    public $codigo;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $idasientopre;

    /**
     * @var string
     */
    public $mensaje;
    
    public static function primaryColumn()
    {
        return "id";
    }

    public static function tableName()
    {
        return "asientospre_variables";
    }

    public function test()
    {
        $utils = $this->toolBox()->utils();
        $this->codigo = $utils->noHtml($this->codigo);
        $this->mensaje = $utils->noHtml($this->mensaje);
        
        return parent::test();
    }
}
