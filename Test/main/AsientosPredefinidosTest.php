<?php


namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Cuenta;
use FacturaScripts\Core\Model\Empresa;
use FacturaScripts\Core\Model\Subcuenta;
use FacturaScripts\Plugins\AsientosPredefinidos\Model\AsientoPredefinido;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

class AsientosPredefinidosTest extends TestCase
{
    use RandomDataTrait, LogErrorsTrait, DefaultSettingsTrait;

    /**
     * @var Empresa
     */
    private $empresa;

    protected function setUp(): void
    {
        $this->empresa = new Empresa();
        $this->empresa->loadFromCode(1);

        $ejercicio = $this->getRandomExercise();
        $ejercicio->idempresa = $this->empresa->idempresa;
        $ejercicio->save();

        // Si no se encuentra el Plan Contable instalado, lo instalamos.
        $numCuentas = count((new Cuenta())->all([], [], 0, 0));
        $numSubCuentas = count((new Subcuenta())->all([], [], 0, 0));
        if ($numCuentas < 800 && $numSubCuentas < 720) {
            $this->installAccountingPlan();
        }
    }

    public function testAsientoPredefinidoNomina()
    {
        $asientoPredefinido = new AsientoPredefinido();
        $asientoPredefinido->loadFromCode(1);

        $asiento = $asientoPredefinido->generate([
            'idempresa' => $this->empresa->idempresa,
            'fecha' => date('now'),
            'var_A' => 0,
            'var_C' => 20,
            'var_L' => 30,
            'var_R' => 40,
            'var_D' => 50,
        ]);

        // Comprobamos que el asiento que ha creado correctamente
        $textoMes = ToolBox::i18n()->trans(strtolower(date('F', strtotime($asiento->fecha))));
        $this->assertEquals('Nómina ' . $textoMes, $asiento->concepto);
        $this->assertEquals(20, $asiento->importe);

        // Comprobamos que las partidas se hayan generado correctamente.
        $partidas = $asiento->getLines();

        $this->assertEquals('4650000000', $partidas[0]->codsubcuenta);
        $this->assertEquals('Pendiente de pago', $partidas[0]->concepto);
        $this->assertEquals(0, $partidas[0]->debe);
        $this->assertEquals(30, $partidas[0]->haber);

        $this->assertEquals('4751000000', $partidas[1]->codsubcuenta);
        $this->assertEquals('Retenciones', $partidas[1]->concepto);
        $this->assertEquals(0, $partidas[1]->debe);
        $this->assertEquals(40, $partidas[1]->haber);

        $this->assertEquals('4760000000', $partidas[2]->codsubcuenta);
        $this->assertEquals('Seguridad Social Acreedora', $partidas[2]->concepto);
        $this->assertEquals(0, $partidas[2]->debe);
        $this->assertEquals(-50, $partidas[2]->haber);

        $this->assertEquals('6400000000', $partidas[3]->codsubcuenta);
        $this->assertEquals('Sueldo', $partidas[3]->concepto);
        $this->assertEquals(50, $partidas[3]->debe);
        $this->assertEquals(0, $partidas[3]->haber);

        $this->assertEquals('6420000000', $partidas[4]->codsubcuenta);
        $this->assertEquals('Seguridad Social Empresa', $partidas[4]->concepto);
        $this->assertEquals(-30, $partidas[4]->debe);
        $this->assertEquals(0, $partidas[4]->haber);

        $asiento->delete();
    }

    public function testAsientoPredefinidoCuotaAutonomo()
    {
        $asientoPredefinido = new AsientoPredefinido();
        $asientoPredefinido->loadFromCode(2);

        $asiento = $asientoPredefinido->generate([
            'idempresa' => $this->empresa->idempresa,
            'fecha' => date('now'),
            'var_A' => 0,
            'var_B' => 123,
        ]);

        // Comprobamos que el asiento que ha creado correctamente
        $textoMes = ToolBox::i18n()->trans(strtolower(date('F', strtotime($asiento->fecha))));
        $this->assertEquals('Cuota de autónomo ' . $textoMes, $asiento->concepto);
        $this->assertEquals(123, $asiento->importe);

        // Comprobamos que las partidas se hayan generado correctamente.
        $partidas = $asiento->getLines();

        $this->assertEquals('4760000000', $partidas[0]->codsubcuenta);
        $this->assertEquals('Cuota autónomo ' . $textoMes, $partidas[0]->concepto);
        $this->assertEquals(0, $partidas[0]->debe);
        $this->assertEquals(123, $partidas[0]->haber);

        $this->assertEquals('6420000000', $partidas[1]->codsubcuenta);
        $this->assertEquals('Cuota autónomo ' . $textoMes, $partidas[1]->concepto);
        $this->assertEquals(123, $partidas[1]->debe);
        $this->assertEquals(0, $partidas[1]->haber);

        $asiento->delete();
    }

    public function testAsientoPredefinidoPagoCuotaAutonomo()
    {
        $asientoPredefinido = new AsientoPredefinido();
        $asientoPredefinido->loadFromCode(3);

        $asiento = $asientoPredefinido->generate([
            'idempresa' => $this->empresa->idempresa,
            'fecha' => date('now'),
            'var_A' => 0,
            'var_B' => 123,
        ]);

        // Comprobamos que el asiento que ha creado correctamente
        $textoMes = ToolBox::i18n()->trans(strtolower(date('F', strtotime($asiento->fecha))));
        $this->assertEquals('N/pago cuota autónomo ' . $textoMes, $asiento->concepto);
        $this->assertEquals(123, $asiento->importe);

        // Comprobamos que las partidas se hayan generado correctamente.
        $partidas = $asiento->getLines();

        $this->assertEquals('4760000000', $partidas[0]->codsubcuenta);
        $this->assertEquals('N/pago cuota autónomo ' . $textoMes, $partidas[0]->concepto);
        $this->assertEquals(123, $partidas[0]->debe);
        $this->assertEquals(0, $partidas[0]->haber);

        $this->assertEquals('5720000000', $partidas[1]->codsubcuenta);
        $this->assertEquals('N/pago cuota autónomo ' . $textoMes, $partidas[1]->concepto);
        $this->assertEquals(0, $partidas[1]->debe);
        $this->assertEquals(123, $partidas[1]->haber);

        $asiento->delete();
    }

    public function testAsientoPredefinidoPagoNomina()
    {
        $asientoPredefinido = new AsientoPredefinido();
        $asientoPredefinido->loadFromCode(4);

        $asiento = $asientoPredefinido->generate([
            'idempresa' => $this->empresa->idempresa,
            'fecha' => date('now'),
            'var_A' => 0,
            'var_B' => 123,
        ]);

        // Comprobamos que el asiento que ha creado correctamente
        $textoMes = ToolBox::i18n()->trans(strtolower(date('F', strtotime($asiento->fecha))));
        $this->assertEquals('Pago nómina ' . $textoMes, $asiento->concepto);
        $this->assertEquals(123, $asiento->importe);

        // Comprobamos que las partidas se hayan generado correctamente.
        $partidas = $asiento->getLines();

        $this->assertEquals('4650000000', $partidas[0]->codsubcuenta);
        $this->assertEquals('N/pago nómina ' . $textoMes, $partidas[0]->concepto);
        $this->assertEquals(123, $partidas[0]->debe);
        $this->assertEquals(0, $partidas[0]->haber);

        $this->assertEquals('5720000000', $partidas[1]->codsubcuenta);
        $this->assertEquals('N/pago nómina ' . $textoMes, $partidas[1]->concepto);
        $this->assertEquals(0, $partidas[1]->debe);
        $this->assertEquals(123, $partidas[1]->haber);

        $asiento->delete();
    }

    public function testMethodTest()
    {
        $asientoPredefinido = new AsientoPredefinido();
        $asientoPredefinido->descripcion = null;
        $asientoPredefinido->concepto = null;
        $this->assertTrue($asientoPredefinido->test());
    }

    public function testUrl()
    {
        $asientoPredefinido = new AsientoPredefinido();

        $this->assertEquals('ListAsiento?activetab=ListAsientoPredefinido', $asientoPredefinido->url());

        $asientoPredefinido->save();

        $this->assertEquals('EditAsientoPredefinido?code=' . $asientoPredefinido->id, $asientoPredefinido->url());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
