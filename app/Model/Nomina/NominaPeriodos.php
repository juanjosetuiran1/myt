<?php

namespace App\Model\Nomina;

use Illuminate\Database\Eloquent\Model;

use App\Model\Nomina\NominaDetalleUno;
use App\Model\Nomina\Nomina;
use App\Model\Nomina\Persona;
use Carbon\Carbon;
use DB;
use Auth;
use App\Traits\Funciones;

class NominaPeriodos extends Model
{
    use Funciones;

    protected $table = "ne_nomina_periodos";


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nro', 'periodo', 'nota', 'fk_idempresa', 'fk_idpersona', 'created_at', 'updated_at', 'fk_idnomina'
    ];


    protected $casts = [
        'fecha_desde' => 'datetime:Y-m-d H:00',
        'fecha_hasta' => 'datetime:Y-m-d H:00',
    ];

    public function persona(){
        return $this->belongsTo(Persona::class, 'fk_idpersona');
    }

    public function nomina(){
        return $this->belongsTo(Nomina::class,'fk_idnomina');
    }

    public function nominaDetallesUno()
    {
        return $this->hasMany(NominaDetalleUno::class, 'fk_nominaperiodo', 'id');
    }

    public function nominaCalculoFijos(){
        return $this->hasMany(NominaCalculoFijo::class, 'fk_nominaperiodo', 'id');
    }

    public function extras(){
        if($this->relationLoaded('nominaDetallesUno')){
            return $this->nominaDetallesUno->whereIn('fk_nomina_cuenta_tipo', [1,2,3])->sum('numero_horas');
        }
        return NominaDetalleUno::where('fk_nominaperiodo', $this->id)->whereIn('fk_nomina_cuenta_tipo', [1,2,3])->sum('numero_horas');
    }

    public function vacaciones(){

        if($this->relationLoaded('nominaDetallesUno')){
            $detalles = $this->nominaDetallesUno->whereIn('fk_nomina_cuenta_tipo', [4,5,6]);
        }else{
            $detalles = NominaDetalleUno::where('fk_nominaperiodo', $this->id)->whereIn('fk_nomina_cuenta_tipo', [4,5,6])->get();
        }

        $dias = 0;
        foreach ($detalles as $detalle) {
            if ($detalle->fecha_inicio) {
                $fechaEmision = Carbon::parse($detalle->fecha_inicio);
                $fechaExpiracion = Carbon::parse($detalle->fecha_fin);
                $dias += self::diffDaysAbsolute($fechaEmision, $fechaExpiracion, ($detalle->nombre == 'VACACIONES' ? true : false)) + ($detalle->nombre == 'VACACIONES' ? 0 : 1);
                $dias += $detalle->dias_compensados_dinero;
                $dias = $dias - $this->validar31($detalle->fecha_inicio, $detalle->fecha_fin);
            }
        }
        return ($dias);
    }

    public static function validar31($start_date,$end_date)
    {
        $start_date = Carbon::parse($start_date);
        $end_date = Carbon::parse($end_date);
        $diff = 0;

        for($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {
            $day = $date->format('d');
            if($day == 31){
                $diff++;
            }
        }

        return $diff;
    }

    public function ingresos(){

        if($this->relationLoaded('nominaDetallesUno')){
            return $this->nominaDetallesUno->whereIn('fk_nomina_cuenta_tipo', [7,8,9])->sum('valor_categoria');
        }

        return NominaDetalleUno::where('fk_nominaperiodo', $this->id)->whereIn('fk_nomina_cuenta_tipo', [7,8,9])->sum('valor_categoria');
    }

    public function deducciones(){

        if($this->relationLoaded('nominaDetallesUno')){
            return $this->nominaDetallesUno->whereIn('fk_nomina_cuenta_tipo', [10,11,12])->sum('valor_categoria');
        }

        return NominaDetalleUno::where('fk_nominaperiodo', $this->id)->whereIn('fk_nomina_cuenta_tipo', [10,11,12])->sum('valor_categoria');
    }

    public function deduccionesObj(){
        return $this->hasMany(NominaDetalleUno::class, 'fk_nominaperiodo')->where('fk_nomina_cuenta', 4);
    }

    public function periodo(){
        $date = Carbon::create($this->year, $this->periodo, 1)->locale('es');
        return ucfirst($date->monthName).' '.$this->year;
    }

    public function tipo(){
        return NominaDetalleUno::where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta_tipo', 4)->first()->nombre;
    }

    public function calculos_vacaciones($tipo = ''){
        $detalles = NominaDetalleUno::where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta_tipo', 4)->get();
        $dias = 0;
        foreach ($detalles as $detalle) {
            if ($detalle->fecha_inicio) {
                $fechaEmision = Carbon::parse($detalle->fecha_inicio);
                $fechaExpiracion = Carbon::parse($detalle->fecha_fin);
                $dias += $fechaExpiracion->diffInDays($fechaEmision);
                $dias += $detalle->dias_compensados_dinero;
                if ($dias>1) {
                    $dias += 1;
                }
            }
        }
        return ($tipo == 'dias') ? $dias : ($dias * $this->valor_total) / 30;
    }


    /**
     * Retornar número de Dias trabajados sin calculos de las categorias (a excepcion de la fecha de contratación).
     *
     * @var array
     */
    public function diasTrabajados(){


        if(!is_object($this->fecha_desde)){
            $inicio = new Carbon($this->fecha_desde);
        }else{
            $inicio = $this->fecha_desde;
        }


        if(!is_object($this->fecha_hasta)){
             $hasta = new Carbon($this->fecha_hasta);
        }else{
             $hasta = $this->fecha_hasta;
        }


        if(!($persona = $this->persona)){
            $persona = $this->nomina->persona;
        }

        $diasRestados = 0;

        if($hasta->format('m') != '02'){
                 if($hasta->format('d') >= 27 && $hasta->format('d') < 30){
                    $hasta->day = 30;
                }

                if($hasta->format('d') >= 31){
                        $hasta->day = 30;
                }
        }

        $fechaContratacion = new Carbon($persona->fecha_contratacion);

        $diasRestados = 0;
        //CONTRATO VIGENTE ACTUAL.
        if($inicio->format('y') == $fechaContratacion->format('y')){
            if($inicio->format('m') == $fechaContratacion->format('m')){
                if(intval($fechaContratacion->format('d')) <= intval($hasta->format('d'))){
                    if(intval($fechaContratacion->format('d')) >= intval($inicio->format('d'))){
                        /* >>> a los dias restados no se le suma +1 por que el dia que entra a trabajar empieza el pago. <<< */
                        $diasRestados += $inicio->diffInDays($fechaContratacion);
                    }
                }
            }
        }

        //CONTRATOS ANTERIORES O LIQUIDADOS EL ONTRATO ACTUAL DE LA PERSONA NO SE LISTA ACÀ
        if($persona->contratos->count() > 0){
            foreach($persona->contratos as $co){
                $fechaTerminacion = $co->comprobanteLiquidacion->fecha_terminacion;
                $fechaTerminacion = new Carbon($fechaTerminacion);

                if($inicio->format('y') == $fechaTerminacion->format('y')){
                    if($inicio->format('m') == $fechaTerminacion->format('m')){
                        if(intval($fechaTerminacion->format('d')) <= intval($hasta->format('d'))){
                                if($fechaContratacion->format('y') == $fechaTerminacion->format('y')){
                                    if($fechaContratacion->format('m') == $fechaTerminacion->format('m')){

                                        //AQUI ENTRA CUANDO SE ECHO EL MISMO MES EL MISMO AÑO Y SE CONTRATO EL MISMO MES Y EL MISMO AÑO
                                        //NO SE HACE NADA PORQUE LOS ANTERIORES CONTRATOS SE LIQUIDAN JUNTO AL COMPROBANTE DE LIQUIDACION POR ENDE NO SE TOMAN EN CUENTA EN ESTE PERIODO.
                                        if($fechaTerminacion->format('d') >= 1){
                                            if($persona->is_liquidado){

                                                $diasRestados = 0;

                                                $diasRestados += $inicio->diffInDays($fechaContratacion);

                                                $diasRestados += $fechaTerminacion->diffInDays($hasta);
                                            }



                                             //
                                        }

                                        //continua por que no es igual el año de contratacion y terminqcion y no es igual el mes de contratcion y liquidacion
                                        continue;
                                    }
                                }
                                // AQUI ENTRA CUANDO LA PERSONA SE LIQUIDO Y NO SE VOLVIO A CONTRATAR EN EL PRESENTE MES O AÑO
                              $diasRestados += $fechaTerminacion->diffInDays($hasta);
                              if($hasta->format('m') == '02'){
                                  $diasRestados += 2;
                              }
                        }
                    }
                }

            }
        }

         /* >>>
        Segun la teoria, al empleado se le paga siempre sobre 15 días si el pago es quincenal o sobre 30 días
        así el mes tenga 28,29,30 o 31 días.
        <<< */
        $dias_trabajados = 0;

        if($this->mini_periodo == 1){
            $dias_trabajados = 30;
        }else if($this->mini_periodo == 2){
            $dias_trabajados = 15;
        }else if($this->mini_periodo == 4){
            $dias_trabajados = 8;
        }

        return $dias_trabajados = $dias_trabajados - $diasRestados;
    }


    /**
     * Método que se encarga de editar o crear salud, pension, subsidio de transporte en la tabla ne_nomina_calculos_fijos
     * y actualiza el total segun las vacaciones, el salario, ingresos adicionales, horas extras y deducciones, pretsaciones y retefuente
     *
     * return json
     */
    public function editValorTotal($calculosFijos = [], $no_edit_calculos_fijos = true){

        $ibcSeguridadSocial = collect([]);

         /* >>> Si no se tiene el pago del empleado obtenemos el valor total de ne_nomina_periodo <<< */
        if($this->pago_empleado === null){
            $this->pago_empleado = $this->valor_total;
        }

        $pagoEmpleado = $this->pago_empleado;

        /* >>>
            Si el periodo no es completo (30 días) entonces ingresa y divide en 2 el pago del empleado ya que cuenta con 2 miniperiodos (2 quincenas)
            (si fuea cada 8 días entonces sería 4 miniperiodos.)
        <<< */
        if($this->periodo != 0){
            $pagoEmpleado = $pagoEmpleado / $this->mini_periodo;
        }

        /* >>> Obtenemos valores_totales (en dinero) de vacaciones, salario, ingresos e incapacidades <<< */
        $ibcSeguridadSocial['vacaciones'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 4)->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);
        $ibcSeguridadSocial['salario']= (($this->pago_empleado / 30) * $this->diasTrabajados());
        $ibcSeguridadSocial['ingresosyExtras'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->whereIn('fk_nomina_cuenta', [1,3])->whereNotIn('fk_nomina_cuenta_tipo', [8, 9])->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);
        $ibcSeguridadSocial['incapacidades'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 5)->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);

        /* >>> Array de incapacidades e iniciamos una variable para ocntar los dias incapacitados <<< */
        $incapacidades = NominaDetalleUno::where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 5)->where('fk_nominaperiodo', $this->id)->get();
        $diasIncapacitado = 0;

        /* >>>
            Recorremos en un array los posibles días incapacitados y obtenemos la fecha inicio y fecha fin
            (si no tiene dias incapacitados no hay nada en fecha inicio y fecha fin)
            (se le suma +1 para que cuente el mismo dia que se incapacitó)
        <<< */

        //nuevo 2024 sunmamos la incapcidad general para calcular el ibc porcentajes y total
        $incapacidad_general = 0;
        foreach($incapacidades as $incapacidad){
            $fechaInicio = new Carbon($incapacidad->fecha_inicio);
            $fechaFin = new Carbon($incapacidad->fecha_fin);

            $diasIncapacitado += self::diffDaysAbsolute($fechaInicio, $fechaFin);

            if($incapacidad->nombre == "INCAPACIDAD GENERAL"){
                $incapacidad_general+=$incapacidad->valor_categoria;
            }
        }

        if($diasIncapacitado){
            $diasIncapacitado++;
        }

        if($ibcSeguridadSocial['incapacidades'] > 0){
            $diasValidosTrabajados = $this->diasTrabajados() - $diasIncapacitado;
            /*>>> el salario se recalcula ya que la persona no trabajo un dia
             formula para obtener el dia trabajado de una persona con incapacidad (50mil pesos) <<<*/

             //logica que en teoria es la misma, decidir cual dejar
             if($this->id != 4483 && $this->id != 4745 && $this->id != 4746){
                $ibcSeguridadSocial['salario'] -= $this->pago_empleado * ((30 / $this->mini_periodo) - $diasValidosTrabajados) / 30;
            }else{
                $ibcSeguridadSocial['salario'] -= $ibcSeguridadSocial['salario'] * ($this->diasTrabajados() - $diasValidosTrabajados) / $this->diasTrabajados();
            }
            /*>>> a las vacaciones se les suma el porcentaje del o los dias que se incapacito <<<*/
            $ibcSeguridadSocial['vacaciones'] += $ibcSeguridadSocial['incapacidades'];
        }
        $licenciaPaga = 0;
        $licencias = NominaDetalleUno::where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 6)->where('fk_nominaperiodo', $this->id)->whereNotNull('fecha_inicio')->get();
        foreach($licencias as $licencia){
            if(!($licencia->is_remunerado())){
                $ibcSeguridadSocial['salario'] -= $licencia->valor_categoria;
            }else{
                $ibcSeguridadSocial['salario'] -= $licencia->valor_categoria;
                $licenciaPaga += $licencia->valor_categoria;
            }
        }

        /* >>> Cálculo final del ibc seguridad social <<< */
        $ibcSeguridadSocial['total'] = $subtotal = $licenciaPaga + $ibcSeguridadSocial['vacaciones'] + ($ibcSeguridadSocial['salario'] - $ibcSeguridadSocial['vacaciones']) +
        $ibcSeguridadSocial['ingresosyExtras'] + $incapacidad_general;

        /* >>> Obtenemos los valores de salud y pension configurados desde el modulo de calculos fijos. <<< */
        $empresa = Auth::user()->empresa;
        $retenSalud = NominaConfiguracionCalculos::where('fk_idempresa',$empresa)->where('nro',2)->first();
        $retenPension = NominaConfiguracionCalculos::where('fk_idempresa',$empresa)->where('nro',3)->first();

        $persona = $this->nomina->persona;

        /* >>> Cálculo de retencion en salud y pensión <<< */

        if($persona->fk_salario_base == 2){
            $calculosFijos['reten_salud'] = (object)['valor' => (($subtotal * (70 / 100)) * $retenSalud->porcDecimal()), 'simbolo' => '-'];
            $calculosFijos['reten_pension'] = (object)['valor' => (($subtotal * (70 / 100)) * $retenPension->porcDecimal()), 'simbolo' => '-'];

            /* provisional
            if($this->pago_empleado >= 4000000 && $this->pago_empleado <= 16000000){
                 $calculosFijos['reten_pension_solidaria'] = (object)['valor' => (($subtotal * (70 / 100)) * (1/100)), 'simbolo' => '-'];
            }
            */

            if($subtotal > 25000000){
                $calculosFijos['reten_salud'] = (object)['valor' => ((25000000) * $retenSalud->porcDecimal()), 'simbolo' => '-'];
                $calculosFijos['reten_pension'] = (object)['valor' => ((25000000) * $retenPension->porcDecimal()), 'simbolo' => '-'];
            }

        }elseif($persona->fk_tipo_contrato == 4 || $persona->fk_tipo_contrato == 6){
            $calculosFijos['reten_salud'] = (object)['valor' => (0), 'simbolo' => '-'];
            $calculosFijos['reten_pension'] = (object)['valor' => (0), 'simbolo' => '-'];
        }else{
            if($ibcSeguridadSocial['vacaciones'] > $ibcSeguridadSocial['salario']){
                $calculosFijos['reten_pension'] = (object)['valor' => ($ibcSeguridadSocial['vacaciones'] * $retenPension->porcDecimal()), 'simbolo' => '-'];
                $calculosFijos['reten_salud'] = (object)['valor' => ($ibcSeguridadSocial['vacaciones'] * $retenSalud->porcDecimal()), 'simbolo' => '-'];   
            }else{
                $calculosFijos['reten_pension'] = (object)['valor' => ($subtotal * $retenPension->porcDecimal()), 'simbolo' => '-'];
                $calculosFijos['reten_salud'] = (object)['valor' => ($subtotal * $retenSalud->porcDecimal()), 'simbolo' => '-'];   
            }
        }

        //pensionado con aporte a salud
        if ($persona->fk_tipo_contrato == 17){
            $calculosFijos['reten_pension'] = (object)['valor' => (0), 'simbolo' => '-'];
        }

        /* >>> Cálculo de dias trabajados  <<< */
        $calculosFijos['dias_trabajados'] =  (object)['valor' => ($this->diasTrabajados() - array_sum($this->diasAusenteDetalle())), 'simbolo' => '#'];

        if($calculosFijos['dias_trabajados']->valor < 0){
            $calculosFijos['dias_trabajados']->valor = 0;
        }
        /* >>>
        Validamos si no viene ya un array con calculos fijos con subsidio de transporte, actualmente se ejecuta desde
        update_vacaciones en NominaController, de lo contrario hacemos el calculo del subisdio de transporte
        con base los dias_trabajados
        <<< */
        if(!isset($calculosFijos['subsidio_transporte'])){
            $subsidioTransporte = NominaConfiguracionCalculos::where('fk_idempresa', $empresa)->where('nro', 1)->first();
            if ($persona->subsidio == 1) {
                $calculosFijos['subsidio_transporte'] = (object)['valor' => ($subsidioTransporte->valor * $calculosFijos['dias_trabajados']->valor / 30), 'simbolo' => '+'];
            }else{
                $calculosFijos['subsidio_transporte'] = (object)['valor' => (0), 'simbolo' => '+'];
            }
        }

        $subtotal += floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 3)->where('fk_nomina_cuenta_tipo', 8)->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);


        if($no_edit_calculos_fijos == true){
            foreach($calculosFijos as $key => $calculoFijo){

                /* >>> Si no hay dias trabajados entonces este se convierte en 0 días <<< */
                if(!isset($calculoFijo->dias_trabajados)){
                    $calculoFijo->dias_trabajados = 0;
                }

                /* >>> Si ya existe un calculo fijo para cierto periodo entonces lo actualizamos, de lo contrario se crea <<< */
                     NominaCalculoFijo::updateOrCreate([
                        'tipo' => $key,
                        'fk_nominaperiodo' => $this->id,
                    ], [
                        'tipo' => $key,
                        'valor' => $calculoFijo->valor,
                        'simbolo' => $calculoFijo->simbolo,
                        'dias_pagos' => $calculoFijo->dias_trabajados,
                        'fk_nominaperiodo' => $this->id,
                        'updated_at' => now(),
                    ]);

            }
        }

        /* >>> Asignamos la data actualizada a la variable calculosFijosCollect <<< */
        $calculosFijosCollect = $this->nominaCalculoFijos;

        /* >>> Sumamos y restamos retenciones en salud y pensión de los calculos que se obtuvieron actualizados <<< */
        $subtotal += $calculosFijosCollect->where('simbolo', '+')->sum('valor');
        $subtotal -= $calculosFijosCollect->where('simbolo', '-')->sum('valor');

        /* >>> Restamos deducciones, prestaciones y retefuente de la cuenta general numero 4 <<< */
        $subtotal -= floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 4)->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);

        /* >>> Asignamos el nuevo total a la nominaperiodo ($this->total) y actualizamos ($this->updae()) <<< */
        $total = $subtotal;
        $this->valor_total = $total;
        $this->update();
    }


    public static function diffDaysAbsolute($start, $end, $isFeriados = false){


        $diasferiados = array();

        $diasferiados = [
            '2022-01-10', '2022-03-21', '2022-04-14', '2022-04-15', '2022-05-01', '2022-05-17',
            '2022-06-20', '2022-06-27', '2022-07-04', '2022-07-20', '2022-08-07', '2022-08-15',
            '2022-10-17', '2022-11-07', '2022-11-14', '2022-12-08', '2022-12-25',
            '2023-01-01', '2023-01-09', '2023-03-20', '2023-04-06', '2023-04-07', '2023-05-01',
            '2023-05-22', '2023-06-12', '2023-06-19', '2023-07-03', '2023-07-20', '2023-08-07',
            '2023-08-21', '2023-10-16', '2023-11-06', '2023-11-13', '2023-12-08', '2023-12-25',
            '2024-01-01', // Año Nuevo
            '2024-01-08', // Día de los Reyes Magos
            '2024-03-25', // Día de San José
            '2024-03-28', // Jueves Santo
            '2024-03-29', // Viernes Santo
            '2024-05-01', // Día del Trabajo
            '2024-05-13', // Ascensión del Señor
            '2024-06-03', // Corphus Christi
            '2024-06-10', // Sagrado Corazón de Jesús
            '2024-07-01', // San Pedro y San Pablo
            '2024-07-20', // Día de la Independencia
            '2024-08-07', // Batalla de Boyacá
            '2024-08-19', // La Asunción de la Virgen
            '2024-10-14', // Día de la Raza
            '2024-11-04', // Todos los Santos
            '2024-11-11', // Independencia de Cartagena
            '2024-12-08', // Día de la Inmaculada Concepción
            '2024-12-25', // Día de Navidad

            '2025-01-01', // Año Nuevo
            '2025-01-06', // Día de los Reyes Magos
            '2025-03-24', // Día de San José
            '2025-04-17', // Jueves Santo
            '2025-04-18', // Viernes Santo
            '2025-05-01', // Día del Trabajo
            '2025-06-02', // Ascensión del Señor
            '2025-06-23', // Corphus Christi
            '2025-06-30', // Sagrado Corazón de Jesús
            '2025-06-30', // San Pedro y San Pablo
            '2025-07-20', // Día de la Independencia
            '2025-08-07', // Batalla de Boyacá
            '2025-08-18', // La Asunción de la Virgen
            '2025-10-13', // Día de la Raza
            '2025-11-03', // Todos los Santos
            '2025-11-17', // Independencia de Cartagena
            '2025-12-08', // Día de la Inmaculada Concepción
            '2025-12-25', // Día de Navidad
        ];


    if(!$isFeriados){
       return $start->diffInDays($end);
    }

    $businessDays = 0;


    while ($start->lte($end)) {
        if (!in_array($start->toDateString(), $diasferiados)) {
            $businessDays++;
        }
        $start->addDay();
    }

    return $businessDays;

    }


    public function resumenTotal(){

        $totalidad = ['pago' => ['salario' => 0, 'subsidioDeTransporte' => 0, 'retencionesDeducciones' => 0, 'total' => 0],
                      'diasTrabajados' => ['diasPeriodo' => 0, 'total' => 0],
                      'salarioSubsidio' => ['salario' => 0, 'subsidioTransporte' => 0, 'total' => 0],
                      'ibcSeguridadSocial' =>  ['salario' => 0, 'total' => 0],
                      'retenciones' => ['salud' => 0, 'pension' => 0, 'total' => 0, 'porcentajeSalud' => 0, 'porcentajePension' => 0],
                      'seguridadSocial' => ['pension' => 0, 'riesgo1' => 0, 'total' => 0],
                      'parafiscales' => ['cajaCompensacion' => 0, 'total' => 0],
                      'provisionPrestacion' => ['cesantias' => 0, 'interesesCesantias' => 0, 'primaServicios' => 0, 'vacaciones' => 0, 'total' => 0],
                      'pagoContratado' => ['total' => 0]
                     ];

        $calculosFijosCollect = $this->nominaCalculoFijos;
        $nominaDetalleUno = $this->nominaDetallesUno;
        $diasVacacionesOtraNomina = 0;
        $diasTrabajados = $this->diasTrabajados();
        $nominaPrincipal = $this->nomina;
        
        $totalidad['ibcSeguridadSocial']['vacaciones'] = 0;
        $valorProporcional = 0;
        
        foreach($nominaPrincipal->nominaperiodos as $periodo){
            if($this->periodo > $periodo->periodo){ 
                
                $vacaciones = $periodo->nominaDetallesUno->where('fk_nomina_cuenta', 2)
                                      ->where('fk_nomina_cuenta_tipo', 4);
        
                foreach($vacaciones as $vac){
                    
                    // Asegúrate de tener campos de fecha en cada $vac (ajusta nombres si es necesario)
                    $inicioVac = Carbon::parse($vac->fecha_inicio);
                    $finVac = Carbon::parse($vac->fecha_fin);
        
                    // Asume que tienes el rango del periodo actual
                    $inicioActual = Carbon::parse($this->fecha_desde);
                    $finActual = Carbon::parse($this->fecha_hasta);
        
                    // Calcular la intersección de fechas
                    $inicioSolapado = $inicioVac->greaterThan($inicioActual) ? $inicioVac : $inicioActual;
                    $finSolapado = $finVac->lessThan($finActual) ? $finVac : $finActual;
                
        
                    if ($inicioSolapado <= $finSolapado) {
                        $diasSolapados = $inicioSolapado->diffInDaysFiltered(function($date) {
                            return $date; // si solo se cuentan días hábiles
                        }, $finSolapado) + 1;
        
                        // Valor proporcional según días (asume que valor_categoria es el valor total de las vacaciones)
                        $diasTotalesVac = $inicioVac->diffInDays($finVac) + 1;
                        
                         $diasTotalesVac2 = $inicioSolapado->diffInDaysFiltered(function($date) {
                            return $date; // si solo se cuentan días hábiles
                        }, $finSolapado) + 1;
                        
                        $valorProporcional = ($vac->valor_categoria / $diasTotalesVac) * $diasSolapados;
        
                        if (!isset($totalidad['ibcSeguridadSocial']['vacaciones'])) {
                            $totalidad['ibcSeguridadSocial']['vacaciones'] = 0;
                        }
                        
                        $totalidad['ibcSeguridadSocial']['vacaciones'] += floatval($valorProporcional);
                        $diasVacacionesOtraNomina += $diasTotalesVac2;

                    }
                    
                }
            }
        }
        
        //Actualizacion del subsidio segun calculo.
        $calculofijo = NominaConfiguracionCalculos::where('fk_idempresa', $nominaPrincipal->fk_idempresa)->get();
        
        $subsidio = $calculosFijosCollect->where('tipo', 'subsidio_transporte')->first();
        if ($subsidio && $diasVacacionesOtraNomina > 0) {
        
            $periodoSalud=$calculosFijosCollect->where('tipo', 'reten_salud')->first();
            $periodoPension=$calculosFijosCollect->where('tipo', 'reten_pension')->first();
            
            // Asegúrate que este método retorne los días válidos
            $subFijo = $calculofijo->where('nombre','Subsidio de transporte')->first();
            $valorPeriodo = $subFijo->valor / $this->mini_periodo;
            $valorMensual = $subFijo->valor;
            $valorDiario = $valorMensual / 30;
            $sub = round($valorDiario * ($diasTrabajados - $diasVacacionesOtraNomina), 0); // o usar 2 decimales si prefieres
            // 3. Actualizar el modelo
            $subsidio->valor = $sub;
            $subsidio->save();
        }
        

        $pagoEmpleado = $this->pago_empleado;
        $totalidad['salarioSubsidio']['salarioCompleto'] = $pagoEmpleado;
        $totalidad['salarioSubsidio']['valorDia'] = $this->pago_empleado / 30;

        if($this->periodo != 0){
            $pagoEmpleado = $pagoEmpleado / $this->mini_periodo;
        }
        $totalidad['salarioSubsidio']['salario'] = $pagoEmpleado;
        $totalidad['salarioSubsidio']['subsidioTransporte'] = floatval($calculosFijosCollect->where('tipo', 'subsidio_transporte')->first()->valor ?? 0);
        $totalidad['salarioSubsidio']['total'] = $totalidad['salarioSubsidio']['salario'] + $totalidad['salarioSubsidio']['subsidioTransporte'];
        $totalidad['diasTrabajados']['diasPeriodo'] = $diasTrabajados;
        // dd($this->diasTrabajados());
        /*>>> Valor vacaciones <<<*/
        $totalidad['ibcSeguridadSocial']['vacaciones']+= floatval($nominaDetalleUno->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 4)->sum('valor_categoria') ?? 0);
        //$totalidad['ibcSeguridadSocial']['vacaciones'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 4)->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);
        $totalidad['ibcSeguridadSocial']['salario']= $pagoEmpleado - $totalidad['ibcSeguridadSocial']['vacaciones'];
        //$totalidad['ibcSeguridadSocial']['ingresosyExtras'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->whereIn('fk_nomina_cuenta', [1,3])->whereNotIn('fk_nomina_cuenta_tipo', [8, 9])->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);
        $totalidad['ibcSeguridadSocial']['ingresosyExtras'] = floatval($nominaDetalleUno->whereIn('fk_nomina_cuenta', [1, 3])->whereNotIn('fk_nomina_cuenta_tipo', [8, 9])->sum('valor_categoria') ?? 0);
        //$totalidad['ibcSeguridadSocial']['incapacidades'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 5)->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);
        $totalidad['ibcSeguridadSocial']['incapacidades'] = floatval($nominaDetalleUno->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 5)->sum('valor_categoria') ?? 0);


        $incapacidades = $nominaDetalleUno->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 5);
        $diasIncapacitado = 0;
        $diasValidosTrabajados = $totalidad['diasTrabajados']['diasPeriodo'];

        foreach($incapacidades as $incapacidad){
            $fechaInicio = new Carbon($incapacidad->fecha_inicio);
            $fechaFin = new Carbon($incapacidad->fecha_fin);

            $diasIncapacitado += self::diffDaysAbsolute($fechaInicio, $fechaFin);
        }
        if($diasIncapacitado){
            $diasIncapacitado++;
        }

        if($totalidad['ibcSeguridadSocial']['incapacidades'] > 0){
            $diasValidosTrabajados = $totalidad['diasTrabajados']['diasPeriodo'] - $diasIncapacitado;
            // dd($totalidad['ibcSeguridadSocial']['salario'],$this->pago_empleado * ($totalidad['diasTrabajados']['diasPeriodo'] - $diasValidosTrabajados) / 30);
            $totalidad['ibcSeguridadSocial']['salario'] -= $this->pago_empleado * ($totalidad['diasTrabajados']['diasPeriodo'] - $diasValidosTrabajados) / 30;
            // $totalidad['ibcSeguridadSocial']['vacaciones'] += $totalidad['ibcSeguridadSocial']['incapacidades'];
        }
        
        // dd($this->diasAusenteDetalle());
        $totalidad['ibcSeguridadSocial']['salarioParcial'] = $diasValidosTrabajados * $totalidad['salarioSubsidio']['valorDia'];
        //Valor real trabajado, contando unicamente con liquidaciones de la persona
        $totalidad['pagoContratado']['total'] = $diasValidosTrabajados * $this->pago_empleado / 30;
        $totalidad['pagoContratado']['deducido'] = $totalidad['pagoContratado']['total'];
        
        // Paso 1: Obtener ausencias previas
        $ausencias = $this->diasAusenteDetalle() ?? [];
        
        // Paso 2: Incluir días de vacaciones al array
        $ausencias['VACACIONES'] = ($ausencias['VACACIONES'] ?? 0) + $diasVacacionesOtraNomina;
        
        // Paso 3: Asignar al array final
        $totalidad['diasTrabajados']['ausencia'] = $ausencias;
        
        $totalidad['diasTrabajados']['total'] = $totalidad['diasTrabajados']['diasPeriodo'] - array_sum($totalidad['diasTrabajados']['ausencia']);
        if($totalidad['diasTrabajados']['total'] < 0){
            $totalidad['diasTrabajados']['total'] = 0;
        }

        $totalidad['ibcSeguridadSocial']['licencias'] = 0;
        $totalidad['pago']['licencias'] = 0;
        $licencias = $nominaDetalleUno->where('fk_nomina_cuenta', 2)->where('fk_nomina_cuenta_tipo', 6)->whereNotNull('fecha_inicio');
        $licenciaNoRemunerada = 0;
        foreach($licencias as $licencia){
            if(!($licencia->is_remunerado())){
                $totalidad['ibcSeguridadSocial']['licencias'] += $licencia->valor_categoria;
                $totalidad['ibcSeguridadSocial']['salario'] -= $licencia->valor_categoria;
                $totalidad['ibcSeguridadSocial']['salarioParcial'] -= $licencia->valor_categoria;
                $licenciaNoRemunerada += $licencia->valor_categoria;
                $totalidad['pagoContratado']['total'] -= $licencia->valor_categoria;
                $totalidad['pagoContratado']['deducido'] -= $licencia->valor_categoria;
            }else{
                $totalidad['pago']['licencias'] += $licencia->valor_categoria;
                $totalidad['ibcSeguridadSocial']['salario'] -= $licencia->valor_categoria;
                $totalidad['ibcSeguridadSocial']['salarioParcial'] -= $licencia->valor_categoria;
                $totalidad['pagoContratado']['deducido'] -= $licencia->valor_categoria;
            }
        }
        $totalDeducidoMenosVacaciones = $totalidad['pagoContratado']['deducido'] - $totalidad['ibcSeguridadSocial']['vacaciones'];
        if($totalDeducidoMenosVacaciones < 0){
            $totalDeducidoMenosVacaciones = 0;
        }

        $totalidad['ibcSeguridadSocial']['total'] = $subtotal = $totalidad['pago']['licencias'] + $totalidad['ibcSeguridadSocial']['vacaciones'] + $totalDeducidoMenosVacaciones + $totalidad['ibcSeguridadSocial']['ingresosyExtras'];

        $totalidad['retenciones']['salud'] = floatval($calculosFijosCollect->where('tipo', 'reten_salud')->first()->valor ?? 0);

        $totalidad['retenciones']['pension'] = floatval($calculosFijosCollect->where('tipo', 'reten_pension')->first()->valor ?? 0);
        $totalidad['retenciones']['total'] += $totalidad['retenciones']['salud'] + $totalidad['retenciones']['pension'];

        $retencionesSalud = $totalidad['retenciones']['salud'];
        $retencionesPension = $totalidad['retenciones']['pension'];

        if ($retencionesSalud > 0 && $retencionesPension > 0) {

            if($totalidad['ibcSeguridadSocial']['total']){

                if($this->nomina->persona->fk_salario_base == 2){

                    $totalidad['retenciones']['porcentajeSalud'] =  round($retencionesSalud * 100 / ($totalidad['ibcSeguridadSocial']['total'] * (70 / 100)));
                    $totalidad['retenciones']['porcentajePension'] =  round($retencionesPension * 100 / ($totalidad['ibcSeguridadSocial']['total'] * (70 / 100)));
                }else{
                    $totalidad['retenciones']['porcentajeSalud'] =  round($retencionesSalud * 100 / $totalidad['ibcSeguridadSocial']['total']);
                    $totalidad['retenciones']['porcentajePension'] =  round($retencionesPension * 100 / $totalidad['ibcSeguridadSocial']['total']);
                }

            }else{

                $totalidad['retenciones']['porcentajeSalud'] =  0;
                $totalidad['retenciones']['porcentajePension'] = 0;

            }
        }

        /*>>> Valor neto pago empleado <<<*/
        $subtotal += floatval($nominaDetalleUno->where('fk_nomina_cuenta', 3)->where('fk_nomina_cuenta_tipo', 8)->sum('valor_categoria') ?? 0);
        $subtotal += $calculosFijosCollect->where('simbolo', '+')->sum('valor');
        $subtotal -= $calculosFijosCollect->where('simbolo', '-')->sum('valor');
        $subtotal -= $deducciones = $totalidad['deducciones']['total'] = floatval($nominaDetalleUno->where('fk_nomina_cuenta', 4)->sum('valor_categoria') ?? 0);
        
        $totalidad['pago']['salario'] = $totalidad['ibcSeguridadSocial']['salario'];
        if($totalidad['pago']['salario'] < 0){
            $totalidad['pago']['salario'] = 0;
        }
        
        //Validacion de retecio y salud/
        if(isset($periodoSalud) && isset($periodoPension)){
            
            $saludFijo = $calculofijo->where('nombre','Retención en salud')->first();
            $pensionFijo = $calculofijo->where('nombre','Retención en pensión')->first();
        
            $periodoSalud->valor = $totalidad['pago']['salario'] * (round($saludFijo->valor) / 100);
            $periodoSalud->save();
            $periodoPension->valor = $totalidad['pago']['salario'] * (round($saludFijo->valor) / 100);
            $periodoPension->save();
        }
    
        $totalidad['ibcSeguridadSocial']['vacaciones']-=$valorProporcional; //se hdevuelve si existe algu valor por que solo se usa para calculos.

        $totalidad['ibcSeguridadSocial']['total_ibcseguridad_social'] = $totalidad['ibcSeguridadSocial']['vacaciones'] +
        $totalidad['ibcSeguridadSocial']['ingresosyExtras'] + $totalidad['ibcSeguridadSocial']['incapacidades'] +
        $totalidad['ibcSeguridadSocial']['licencias'];
        $totalidad['pago']['total'] = $totalidad['pago']['salario']  +
        $totalidad['salarioSubsidio']['subsidioTransporte'] +
        $totalidad['ibcSeguridadSocial']['total_ibcseguridad_social'] -
        $totalidad['retenciones']['total'];

        if($totalidad['pago']['salario'] < 0){
            $totalidad['pago']['salario'] = 0;
        }

       // $totalidad['pago']['extrasOrdinariasRecargos'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 1)->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);
        $totalidad['pago']['extrasOrdinariasRecargos'] = floatval(
        $nominaDetalleUno
            ->where('fk_nomina_cuenta', 1)
            ->sum('valor_categoria') ?? 0
        );
        $totalidad['pago']['vacaciones'] = $totalidad['ibcSeguridadSocial']['vacaciones'];

        //$totalidad['pago']['ingresosAdicionales'] = floatval(NominaDetalleUno::select(DB::raw("SUM(valor_categoria) as valor_total"))->where('fk_nominaperiodo', $this->id)->where('fk_nomina_cuenta', 3)->whereNotIn('fk_nomina_cuenta_tipo', [8, 9])->groupBy('fk_nominaperiodo')->first()->valor_total ?? 0);
        $totalidad['pago']['ingresosAdicionales'] = floatval(
            $nominaDetalleUno
                ->where('fk_nomina_cuenta', 3)
                ->whereNotIn('fk_nomina_cuenta_tipo', [8, 9])
                ->sum('valor_categoria') ?? 0
        );

        $totalidad['pago']['subsidioDeTransporte'] = floatval($calculosFijosCollect->where('tipo', 'subsidio_transporte')->first()->valor ?? 0);
        $totalidad['pago']['retencionesDeducciones'] = $totalidad['retenciones']['total'] + $deducciones;

        $porcentajeRiesgo = 0.00522;

        if($claseRiesgo = $this->nomina->persona->clase_riesgo()){
            if($claseRiesgo == 'Máximo - Riesgo 5'){
                $porcentajeRiesgo = 0.0696;
            }else if($claseRiesgo == 'Bajo - riesgo 2'){
                $porcentajeRiesgo = 0.01044;
            }else if($claseRiesgo == 'Medio - Riesgo 3'){
                $porcentajeRiesgo = 0.02436;
            }else if($claseRiesgo == 'Alto - riesgo 4'){
                $porcentajeRiesgo = 0.0435;
            }
        }
        
        $totalidad['seguridadSocial']['valorRiesgo'] = $porcentajeRiesgo;
        $totalidad['seguridadSocial']['pension'] = $totalidad['ibcSeguridadSocial']['total'] * 0.12;
        $totalidad['seguridadSocial']['riesgo1'] = $totalidad['ibcSeguridadSocial']['salario'] * $porcentajeRiesgo;
        $totalidad['seguridadSocial']['total'] = $totalidad['seguridadSocial']['pension'] + $totalidad['seguridadSocial']['riesgo1'];

        $totalidad['parafiscales']['cajaCompensacion'] = $totalidad['ibcSeguridadSocial']['total'] * 0.04;
        $totalidad['parafiscales']['total'] = $totalidad['parafiscales']['cajaCompensacion'];
        $totalidad['provisionPrestacion']['cesantias'] = $totalidad['salarioSubsidio']['total'] * (8.33 / 100);
        $totalidad['provisionPrestacion']['interesesCesantias'] = $totalidad['provisionPrestacion']['cesantias'] * 0.12;
        $totalidad['provisionPrestacion']['primaServicios'] = $totalidad['salarioSubsidio']['total'] * (8.33 / 100);
        $totalidad['provisionPrestacion']['vacaciones'] = $totalidad['ibcSeguridadSocial']['total'] * (4.17 / 100);
        $totalidad['provisionPrestacion']['total'] = $totalidad['provisionPrestacion']['cesantias'] + $totalidad['provisionPrestacion']['interesesCesantias'] + $totalidad['provisionPrestacion']['primaServicios'] + $totalidad['provisionPrestacion']['vacaciones'];
        
        $this->valor_total = $totalidad['pago']['total'];
        $this->save();
        return $totalidad;
    }
    
    public function diasAusenteDetalle()
    {
        $detalles = NominaDetalleUno::where('fk_nominaperiodo', $this->id)
            ->where('fk_nomina_cuenta', 2)
            ->get();
        $dias = [];

        foreach ($detalles as $detalle) {
            if ($detalle->fecha_inicio) {
                if (!$detalle->nombre) {
                    $detalle->nombre = 'sin definir';
                }

                $fechaEmision = Carbon::parse($detalle->fecha_inicio);
                $fechaExpiracion = Carbon::parse($detalle->fecha_fin);

                if (!isset($dias[$detalle->nombre])) {
                    $dias[$detalle->nombre] = 0;
                }

                // Ajuste para excluir el día 31 del cálculo
                $diasCalculados = $this->diffDaysExcluding31($fechaEmision, $fechaExpiracion);

                // Suma los días calculados
                $dias[$detalle->nombre] += $diasCalculados;

                // // Si no son vacaciones, suma 1 día adicional
                // if ($detalle->nombre !== 'VACACIONES') {
                //     $dias[$detalle->nombre] += 1;
                // }
            }
        }

        return $dias;
    }

    /**
     * Calcula los días entre dos fechas excluyendo el día 31 de cualquier mes.
    */
    public static function diffDaysExcluding31(Carbon $start, Carbon $end)
    {
        $currentDate = $start->copy();
        $totalDays = 0;

        while ($currentDate->lessThanOrEqualTo($end)) {
            if ($currentDate->day !== 31) {
                $totalDays++;
            }
            $currentDate->addDay();
        }

        return $totalDays;
    }

    /**
     *
     * Método para obtener una coleccion de rangos de fechas desde y hasta de las nominas que se han generado en una empresa
     * ejm: (1-15 oct 2021 - 16-31oct 2021 - 1-15 nov 2021 - 16 - 30nov 2021)
     *
     * return json
     */
    public static function rangosFechas(){

        $empresa = Auth::user()->empresa;
        $rangoFechas = Nomina::join('ne_nomina_periodos as np','ne_nomina.id','=','np.fk_idnomina')
        ->where('ne_nomina.fk_idempresa',$empresa)
        ->where('np.isPagado',1)
        ->select('np.id','np.fecha_desde','np.fecha_hasta')
        ->groupBy('np.fecha_desde')->get();

        /*>>> Organizamos la data para separar cada rango en un espacio de un array unico <<<*/
        $rangoFinales = [];
        foreach($rangoFechas as $rango){
            array_push($rangoFinales,$rango->fecha_desde);
            array_push($rangoFinales,$rango->fecha_hasta);
        }

        return (object)$rangoFinales;

    }

    public function updateCalculosNomina(){

        $calculos_nomina_periodo = $this->resumenTotal();
        if($calculos_nomina_periodo['diasTrabajados']['total'] == 0){

            $empresa = Auth::user()->empresa;

            $nomina_calculos_fijos = NominaCalculoFijo::where('fk_nominaperiodo',$this->id)
            ->where('tipo','reten_salud')
            ->orWhere('fk_nominaperiodo',$this->id)
            ->where('tipo','reten_pension')
            ->get();

            $retenSalud = NominaConfiguracionCalculos::where('fk_idempresa', $empresa)->where('nro', 2)->first();
            $retenPension = NominaConfiguracionCalculos::where('fk_idempresa', $empresa)->where('nro', 3)->first();

            foreach($nomina_calculos_fijos as $salud_pension){

                $reten_salud_new = new NominaCalculoFijo();
                if($salud_pension->tipo == "reten_salud"){
                    $valor_new = ($calculos_nomina_periodo['pago']['vacaciones']) * $retenSalud->porcDecimal();
                }

                else if($salud_pension->tipo == "reten_pension"){
                    $valor_new = ($calculos_nomina_periodo['pago']['vacaciones']) * $retenPension->porcDecimal();
                }

                $update =  NominaCalculoFijo::updateOrCreate([
                    'tipo' => $salud_pension->tipo,
                    'fk_nominaperiodo' => $salud_pension->fk_nominaperiodo,
                ], [
                    'tipo' => $salud_pension->tipo,
                    'valor' => $valor_new,
                    'simbolo' => $salud_pension->simbolo,
                    'dias_pagos' => $salud_pension->dias_pagos,
                    'fk_nominaperiodo' => $salud_pension->fk_nominaperiodo,
                    'updated_at' => now(),
                ]);

            }
            return $calculos_nomina_periodo['pago']['total'];
            // - $calculos_nomina_periodo['pago']['retencionesDeducciones'];
        }
    }

}
