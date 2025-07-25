@extends('layouts.app')
@section('content')

	<form method="POST" action="{{ route('ventas-externas.update', $contacto->id) }}" style="padding: 2% 3%;" role="form" class="forms-sample" id="form-contacto" enctype="multipart/form-data">
  		<input name="_method" type="hidden" value="PATCH">
  		{{ csrf_field() }}
  		<input type="hidden"  id="idmunicipio" value="{{ $contacto->fk_idmunicipio }}">
  			<input type="hidden"  id="iddepartamento" value="{{ $contacto->fk_iddepartamento }}">
  			<input type="hidden" id="pastpais" value="{{$contacto->fk_idpais}}">
  		<div class="row">
  			<div class="form-group col-md-3">
	  			<label class="control-label">Tipo de Identificación <span class="text-danger">*</span></label>
	  			<select class="form-control selectpicker" name="tip_iden" id="tip_iden" required="" onchange="searchDV(this.value)" title="Seleccione">
	  				@foreach($identificaciones as $identificacion)
                  		<option {{$contacto->tip_iden==$identificacion->id?'selected':''}} value="{{$identificacion->id}}" title="{{$identificacion->mini()}}" >{{$identificacion->identificacion}}</option>
	  				@endforeach
                </select>
				<span class="help-block error">
		        	<strong>{{ $errors->first('tip_iden') }}</strong>
		        </span>
			</div>
			<div class="form-group col-md-3">
	  			<label class="control-label">Identificación <span class="text-danger">*</span></label>
				<input type="text" class="form-control" name="nit" id="nit" required="" maxlength="20" value="{{$contacto->nit}}">
				<span class="help-block error">
					<strong>{{ $errors->first('nit') }}</strong>
				</span>
			</div>

			<div class="form-group col-md-1" style="display: none;" id="dvnit">
			<label class="control-label">DV <span class="text-danger">*</span></label>
			<input type="text" class="form-control" name="dv" id="dv" disabled required="" maxlength="20" value="">
			<input type="hidden" name="dvoriginal" id="dvoriginal" value="">
			<span class="help-block error">
				<strong>{{ $errors->first('dv') }}</strong>
			</span>
		</div>
		<div class="form-group col-md-3">
			<label class="control-label">Nombres <span class="text-danger">*</span></label>
			<input type="text" class="form-control" name="nombre" id="nombre" required="" maxlength="200" value="{{$contacto->nombre}}">
			<span class="help-block error">
				<strong>{{ $errors->first('nombre') }}</strong>
			</span>
		</div>
		<div class="form-group col-md-3">
			<label class="control-label">Apellido 1 @if($contacto->tipo_contacto == 0) <span class="text-danger">*</span> @endif</label>
			<input type="text" class="form-control" name="apellido1" id="apellido1" {{ $contacto->tipo_contacto == 0 ? 'required' : '' }} maxlength="200" value="{{$contacto->apellido1}}">
			<span class="help-block error">
				<strong>{{ $errors->first('apellido1') }}</strong>
			</span>
		</div>
		<div class="form-group col-md-3">
			<label class="control-label">Apellido 2</label>
			<input type="text" class="form-control" name="apellido2" id="apellido2" maxlength="200" value="{{$contacto->apellido2}}">
			<span class="help-block error">
				<strong>{{ $errors->first('apellido2') }}</strong>
			</span>
		</div>
  	</div>

	<div class="row">
		<div class="form-group col-md-3">
			<label class="control-label">País <span class="text-danger">*</span></label>
			<select class="form-control selectpicker" name="pais" id="pais" required="" title="Seleccione" data-live-search="true" data-size="5" onchange="validateCountry(this.value)">
				@foreach($paises as $pais)
				<option value="{{$pais->codigo}}" {{ $contacto->fk_idpais == $pais->codigo ? 'selected' : '' }}>{{$pais->nombre}}</option>
				@endforeach
			</select>
		</div>

		<div class="form-group col-md-3" id="validatec1">
			<label class="control-label">Departamento <span class="text-danger">*</span></label>
			<select class="form-control selectpicker" name="departamento" id="departamento" title="Seleccione" data-live-search="true" data-size="5" onchange="searchMunicipality(this.value)" required="">
				@foreach($departamentos as $departamento)
				<option value="{{ $departamento->id }}"
					{{ $contacto->fk_iddepartamento == $departamento->id ? 'selected' : '' }}
					>{{ $departamento->nombre }}</option>
				@endforeach
			</select>
		</div>

        <div class="form-group col-md-3" id="validatec2">
            <label class="control-label">Municipio <span class="text-danger">*</span></label>
            <select class="form-control selectpicker" name="municipio" id="municipio" required="" title="Seleccione" data-live-search="true" data-size="5">
                <option selected value="{{ $contacto->fk_idmunicipio }}">  {{ $contacto->municipio()->nombre }}</option>
            </select>
        </div>


		<div class="form-group col-md-3" id="validatec3">
			<label class="control-label">Código Postal</label>
			<a><i data-tippy-content="Si desconoces tu código postal <a target='_blank' href='http://visor.codigopostal.gov.co/472/visor/'>haz click aquí</a>" class="icono far fa-question-circle"></i></a>
			<input type="text" class="form-control" id="cod_postal" name="cod_postal" maxlength="200"  value="{{$contacto->cod_postal}}">
		</div>

		<div class="form-group col-md-3">
			<label class="control-label">Dirección <span class="text-danger">*</span></label>
			<input type="text" class="form-control" id="direccion" name="direccion" maxlength="200"  value="{{$contacto->direccion}}">
			<span class="help-block error">
				<strong>{{ $errors->first('direccion') }}</strong>
			</span>
		</div>

        <div class="form-group col-md-3">
			<label class="control-label">Corregimiento/Vereda</label>
			<input type="text" class="form-control" id="vereda" name="vereda" maxlength="200"  value="{{$contacto->vereda}}">
			<span class="help-block error">
				<strong>{{ $errors->first('vereda') }}</strong>
			</span>
		</div>

		<div class="form-group col-md-3">
			<label class="control-label">Barrio</label>
			<input type="text" class="form-control" id="barrio" name="barrio" maxlength="200"  value="{{$contacto->barrio}}">
			<span class="help-block error">
				<strong>{{ $errors->first('barrio') }}</strong>
			</span>
		</div>

        <div class="form-group col-md-3">
			<label class="control-label" for="email">Correo Electrónico </label>
			<input type="email" class="form-control" id="email" name="email" data-error="Dirección de correo electrónico invalida" maxlength="100"  value="{{$contacto->email}}" {{--{{ Auth::user()->empresa()->form_fe == 1 ? 'required' : ''}}--}}>
			<div class="help-block error with-errors"></div>
			<span class="help-block error">
				<strong>{{ $errors->first('email') }}</strong>
			</span>
		</div>
		@if($contacto->fk_idmunicipio == null && $contacto->ciudad != "")
		<div class="form-group col-md-3">
			<label class="control-label">Ciudad (antes)</label>
			<input type="text" class="form-control" id="ciudad" name="ciudad" maxlength="200"  value="{{$contacto->ciudad}}">
			<span class="help-block error">
				<strong>{{ $errors->first('ciudad') }}</strong>
			</span>
		</div>
		@endif

	</div>
  		<div class="row">
  			<div class="form-group col-md-3">
	  			<label class="control-label">Teléfono</label>
	  			<input type="text" class="form-control" id="telefono1" name="telefono1" maxlength="15" value="{{$contacto->telef('telefono1')}}">
				<span class="help-block error">
		        	<strong>{{ $errors->first('telefono1') }}</strong>
		        </span>
			</div>
			<div class="form-group col-md-3">
	  			<label class="control-label">Celular</label>
	  			<input type="text" class="form-control" id="celular" name="celular"  maxlength="15" value="{{$contacto->telef('celular')}}">
				<span class="help-block error">
		        	<strong>{{ $errors->first('celular') }}</strong>
		        </span>
			</div>
  			<div class="form-group col-md-3">
	  			<label class="control-label">Teléfono 2</label>
	  			<input type="text" class="form-control" id="telefono2" name="telefono2" maxlength="15" value="{{$contacto->telef('telefono2')}}">
				<span class="help-block error">
		        	<strong>{{ $errors->first('telefono2') }}</strong>
		        </span>
			</div>
			<div class="form-group col-md-3">
	  			<label class="control-label">Fax</label>
	  			<input type="text" class="form-control" id="fax" name="fax" maxlength="15" value="{{$contacto->telef('fax')}}">
	  			<span class="help-block error">
		        	<strong>{{ $errors->first('fax') }}</strong>
		        </span>
			</div>
            <div class="form-group col-md-3">
                <label class="control-label">Monitoreo</label>
                <input type="text" class="form-control" id="monitoreo" name="monitoreo" maxlength="15" value="{{$contacto->telef('monitoreo')}}">
                <span class="help-block error">
                    <strong>{{ $errors->first('monitoreo') }}</strong>
                </span>
            </div>
            <div class="form-group col-md-3">
                <label class="control-label">Refiere</label>
                <input type="text" class="form-control" id="refiere" name="refiere" maxlength="15" value="{{$contacto->telef('refiere')}}" >
                <span class="help-block error">
                    <strong>{{ $errors->first('refiere') }}</strong>
                </span>
            </div>
            <div class="form-group col-md-3">
                <label class="control-label">Combo INT y TV</label>
                <input type="text" class="form-control" id="combo_int_tv" name="combo_int_tv" maxlength="15" value="{{$contacto->telef('combo_int_tv')}}" >
                <span class="help-block error">
                    <strong>{{ $errors->first('combo_int_tv') }}</strong>
                </span>
            </div>
            <div class="form-group col-md-3">
                <label class="control-label">Referencia I</label>
                <input type="text" class="form-control" id="referencia_1" name="referencia_1" maxlength="15" value="{{$contacto->telef('referencia_1')}}" >
                <span class="help-block error">
                    <strong>{{ $errors->first('referencia_1') }}</strong>
                </span>
            </div>
            <div class="form-group col-md-3">
                <label class="control-label">Referencia II</label>
                <input type="text" class="form-control" id="referencia_2" name="referencia_2" maxlength="15" value="{{$contacto->telef('referencia_2')}}" >
                <span class="help-block error">
                    <strong>{{ $errors->first('referencia_2') }}</strong>
                </span>
            </div>
            <div class="form-group col-md-3">
                <label class="control-label">Cierra Venta</label>
                <input type="text" class="form-control" id="cierra_venta" name="cierra_venta" maxlength="15" value="{{$contacto->telef('cierra_venta')}}" >
                <span class="help-block error">
                    <strong>{{ $errors->first('cierra_venta') }}</strong>
                </span>
            </div>
            <div class="form-group col-md-3">
                <label class="control-label">Planes</label>
                <select class="form-control  selectpicker" name="plan" id="plan" required="" title="Seleccione" data-live-search="true" data-size="5" onchange="validateCountry(this.value)">
                    @foreach($planes as $plan)
                      <option value="{{ $plan->id }}"  {{ $contacto->plan_velocidad == $plan->id ? 'selected' : '' }}> {{$plan->producto}} </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-3">
                <label class="control-label">Costo Instalación</label>
                <input type="text" class="form-control" id="costo_instalacion" name="costo_instalacion" maxlength="50" value="{{$contacto->telef('costo_instalacion')}}" >
                <span class="help-block error">
                    <strong>{{ $errors->first('costo_instalacion') }}</strong>
                </span>
            </div>
  		</div>


  		<div class="row">
  			<div class="form-group col-md-3">
                <label class="control-label">Estrato</label>
                <select class="form-control selectpicker" id="estrato" name="estrato" title="Seleccione" data-live-search="true" data-size="5">
                    <option value="1" {{ $contacto->estrato == 1 ? 'selected':'' }}>1</option>
                    <option value="2" {{ $contacto->estrato == 2 ? 'selected':'' }}>2</option>
                    <option value="3" {{ $contacto->estrato == 3 ? 'selected':'' }}>3</option>
                    <option value="4" {{ $contacto->estrato == 4 ? 'selected':'' }}>4</option>
                    <option value="5" {{ $contacto->estrato == 5 ? 'selected':'' }}>5</option>
                    <option value="6" {{ $contacto->estrato == 6 ? 'selected':'' }}>6</option>
                </select>
                <span class="help-block error">
                    <strong>{{ $errors->first('estrato') }}</strong>
                </span>
            </div>
            <div class="col-md-3 form-group">
	            <label class="control-label">Vendedor <a><i data-tippy-content="Seleccione el vendedor del contrato. Para agregar otros vendedores ingrese al <a href='{{ route('vendedores.index') }}' target='_blank'>módulo de vendedores</a>" class="icono far fa-question-circle"></i></a></label>
	            <div class="input-group mb-2">
	                <select class="form-control selectpicker" name="vendedor" id="vendedor" title="Seleccione" data-live-search="true" data-size="5" required>
	                    @foreach($vendedores as $vendedor)
	                    <option value="{{$vendedor->id}}" {{$contacto->vendedor_externa==$vendedor->id?'selected':''}} {{old('vendedor')==$vendedor->id?'selected':''}}>{{$vendedor->nombre}}</option>
	                    @endforeach
	                </select>
	                <span style="color: red;">
	                    <strong>{{ $errors->first('vendedor') }}</strong>
	                </span>
	            </div>
	        </div>
	        <div class="col-md-3 form-group">
	            <label class="control-label">Canal de Ventas <a><i data-tippy-content="Seleccione el canal de venta. Para agregar otros vendedores ingrese al <a href='{{ route('canales.index') }}' target='_blank'>módulo de canales de venta</a>" class="icono far fa-question-circle"></i></a></label>
	            <div class="input-group mb-2">
	                <select class="form-control selectpicker" name="canal" id="canal" title="Seleccione" data-live-search="true" data-size="5" required>
	                    @foreach($canales as $canal)
	                    <option value="{{$canal->id}}" {{$contacto->canal_externa==$canal->id?'selected':''}} {{old('canal')==$canal->id?'selected':''}}>{{$canal->nombre}}</option>
	                    @endforeach
	                </select>
	                <span style="color: red;">
	                    <strong>{{ $errors->first('canal') }}</strong>
	                </span>
	            </div>
	        </div>
	        @if(Auth::user()->empresa()->oficina)
	        <div class="form-group col-md-3">
	        	<label class="control-label">Oficina Asociada <span class="text-danger">*</span></label>
	        	<select class="form-control selectpicker" name="oficina" id="oficina" required="" title="Seleccione" data-live-search="true" data-size="5">
	        		@foreach($oficinas as $oficina)
	        		<option value="{{$oficina->id}}" {{ $oficina->id == auth()->user()->oficina ? 'selected' : '' }}>{{$oficina->nombre}}</option>
	        		@endforeach
	        	</select>
	        </div>
	        @endif
  		    <div class="form-group col-md-3 d-none">
	  			<label class="control-label">Tipo de Contacto <span class="text-danger">*</span></label>
				<div class="form-check form-check-flat">
                    <label class="form-check-label">
                      <input type="checkbox" class="form-check-input" name="contacto[]" value="0" @if($contacto->tipo_contacto==0  ||  $contacto->tipo_contacto==2 ) checked="" @endif> Cliente
                    <i class="input-helper"></i></label>
                  </div>
                  <div class="form-check form-check-flat">
                    <label class="form-check-label">
                      <input type="checkbox" class="form-check-input" name="contacto[]" value="1" @if($contacto->tipo_contacto==1 ||  $contacto->tipo_contacto==2 ) checked="" @endif> Proveedor
                    <i class="input-helper"></i></label>
                  </div>
                  <span class="help-block error">
					<strong>{{ $errors->first('contacto') }}</strong>
				</span>
			</div>
  			<div class="form-group col-md-6">
  				<label class="control-label">Observaciones</label>
  				<textarea class="form-control" name="observaciones"  rows="10" cols="50" >{{$contacto->observaciones}}</textarea>
  				<span class="help-block error">
  					<strong>{{ $errors->first('observaciones') }}</strong>
  				</span>
  			</div>
  		</div>

		<small>Los campos marcados con <span class="text-danger">*</span> son obligatorios</small>
  		
  		<hr>
  		
  		<!-- Sección para mostrar adjuntos existentes -->
  		<div class="row">
  			<div class="col-md-12">
  				<h5><i class="fas fa-paperclip"></i> Documentos Adjuntos Existentes</h5>
  				<div id="adjuntos-existentes">
  					<!-- Los adjuntos se cargarán aquí -->
  				</div>
  			</div>
  		</div>
  		
  		<!-- Sección para agregar nuevos adjuntos -->
  		<div class="row mt-4">
  			<div class="col-md-12">
  				<h5><i class="fas fa-plus"></i> Agregar Nuevos Documentos</h5>
  				<p class="text-muted">Puedes agregar hasta 3 documentos adicionales (PDF, JPG, PNG, DOCX - Máximo 5MB cada uno)</p>
  				<div class="alert alert-info">
  					<small><i class="fas fa-info-circle"></i> Total de adjuntos permitidos por venta externa: <strong>10 archivos</strong></small>
  				</div>
  			</div>
  		</div>

  		<div class="row">
  			<div class="form-group col-md-4">
  				<label class="control-label">Nuevo Documento 1</label>
  				<input type="file" class="form-control-file" name="nuevo_adjunto1" id="nuevo_adjunto1" accept=".pdf,.jpg,.jpeg,.png,.docx,.doc">
  				<small class="text-muted">Máximo 5MB</small>
  			</div>
  			<div class="form-group col-md-4">
  				<label class="control-label">Nuevo Documento 2</label>
  				<input type="file" class="form-control-file" name="nuevo_adjunto2" id="nuevo_adjunto2" accept=".pdf,.jpg,.jpeg,.png,.docx,.doc">
  				<small class="text-muted">Máximo 5MB</small>
  			</div>
  			<div class="form-group col-md-4">
  				<label class="control-label">Nuevo Documento 3</label>
  				<input type="file" class="form-control-file" name="nuevo_adjunto3" id="nuevo_adjunto3" accept=".pdf,.jpg,.jpeg,.png,.docx,.doc">
  				<small class="text-muted">Máximo 5MB</small>
  			</div>
  		</div>
  		
  		<hr>
  		<div class="row" style="text-align: right;">
  			<div class="col-md-12">
				<a href="{{route('ventas-externas.index')}}" class="btn btn-outline-light" >Cancelar</a>
  				<button type="submit" class="btn btn-success">Guardar</button>
  			</div>
  		</div>

	  </form>
@endsection

@section('scripts')
	<script src="{{asset('lowerScripts/guiaenvio/guiaenvio.js')}}"></script>

    <script type="text/javascript">
        $(document).ready(function(){
            //searchMunicipality($("#departamento").val() , {{$contacto->fk_idmunicipio}});

            $("#municipio").val({{$contacto->fk_idmunicipio}}).selectpicker('refresh');
            var option = document.getElementById('tip_iden').value;
                if (option == 6) {
                    searchDV($("#tip_iden").val());
                }
                
            // Cargar adjuntos existentes
            cargarAdjuntosExistentes();
            
            // Agregar event listeners para los nuevos adjuntos
            $('#nuevo_adjunto1, #nuevo_adjunto2, #nuevo_adjunto3').on('change', function() {
                mostrarVistaPrevia(this);
            });
        });
        
        function cargarAdjuntosExistentes() {
            $.ajax({
                url: `/software/empresa/ventas-externas/adjuntos/{{$contacto->id}}`,
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    let html = '<div class="row">';
                    
                    if (data.adjuntos.length === 0) {
                        html += '<div class="col-12"><p class="text-muted">No hay documentos adjuntos para esta venta externa.</p></div>';
                    } else {
                        data.adjuntos.forEach(function(adjunto) {
                            html += `
                                <div class="col-md-3 mb-3" id="adjunto-${adjunto.id}">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-file fa-2x mb-2 text-info"></i>
                                            <h6 class="card-title" style="font-size: 0.8rem;">${adjunto.nombre_archivo}</h6>
                                            <small class="text-muted">${adjunto.tipo_documento}</small><br>
                                            <div class="mt-2">
                                                <a href="/${adjunto.ruta_archivo}" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-download"></i> Ver
                                                </a>
                                                <button onclick="eliminarAdjunto(${adjunto.id})" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    
                    html += '</div>';
                    
                    // Agregar contador de adjuntos
                    const totalAdjuntos = data.adjuntos.length;
                    const adjuntosRestantes = 10 - totalAdjuntos;
                    html += `
                        <div class="alert alert-light">
                            <small>
                                <i class="fas fa-paperclip"></i> 
                                Adjuntos actuales: <strong>${totalAdjuntos}</strong> | 
                                Disponibles: <strong>${adjuntosRestantes}</strong>
                            </small>
                        </div>
                    `;
                    
                    $('#adjuntos-existentes').html(html);
                },
                error: function() {
                    $('#adjuntos-existentes').html('<p class="text-danger">Error al cargar los adjuntos</p>');
                }
            });
        }
        
        function eliminarAdjunto(adjuntoId) {
            if (confirm('¿Está seguro de que desea eliminar este adjunto?')) {
                $.ajax({
                    url: `/software/empresa/ventas-externas/adjuntos/eliminar/${adjuntoId}`,
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(data) {
                        if (data.success) {
                            $('#adjunto-' + adjuntoId).fadeOut(300, function() {
                                $(this).remove();
                                // Recargar la sección de adjuntos existentes para actualizar el contador
                                cargarAdjuntosExistentes();
                            });
                            alert('Adjunto eliminado correctamente');
                        } else {
                            alert('Error al eliminar el adjunto');
                        }
                    },
                    error: function() {
                        alert('Error al eliminar el adjunto');
                    }
                });
            }
        }
        
        function mostrarVistaPrevia(input) {
            if (input.files && input.files[0]) {
                const archivo = input.files[0];
                const tamaño = archivo.size / 1024 / 1024; // Convertir a MB
                
                // Obtener el contenedor de vista previa
                let previsualizacion = $(input).siblings('.vista-previa');
                if (previsualizacion.length === 0) {
                    previsualizacion = $('<div class="vista-previa mt-2"></div>');
                    $(input).after(previsualizacion);
                }
                
                let html = `
                    <div class="alert alert-info p-2">
                        <small>
                            <i class="fas fa-file"></i> 
                            <strong>${archivo.name}</strong><br>
                            Tamaño: ${tamaño.toFixed(2)} MB
                        </small>
                `;
                
                if (tamaño > 5) {
                    html += `<br><span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Archivo muy grande (máximo 5MB)</span>`;
                }
                
                html += `</div>`;
                previsualizacion.html(html);
            }
        }
	</script>
@endsection
