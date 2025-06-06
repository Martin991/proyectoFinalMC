<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    public function createUserAdmin()
    {
        User::create([
            'name' => 'Juan Martin Castillo Peña',
            'email' => 'martin.castillo@pjedomex.gob.mx',
            'password' => Hash::make('123456'),
            'photo' => '',
            'status' => 1,
            'id_branch' => 0,
            'role' => 'Administrador',
            'last_login' => '',
        ]);
        return 'Usuario creado';
    }

    public function usersDatas(Request $request)
    {
        $resultGral = array('status' => 'success', 'totalCount' => 0, 'msg' => 'TODO BIEN', "data" => array());
        
        // VALIDAMOS SI ES PROCESO PARA DATATABLE O NORMAL
        $dataTable = ($request->input('dataTable') == 'S' ? true : false);
        try {

            // RESCATAMOS LOS PARAMETROS
            $activo = $request->input('activo');

            // CONSTRUIMOS QUERY
            $query = User::query();
            if (!is_null($activo) && $activo !== '') {
                $query->where('activo', $activo);
            }
            
            if ($dataTable) { // DATATABLE
                $resultGral = DataTables::of($query)->make(true);
                
            }else{ // NORMAL
                $idUsuario = $request->input('id');
                if (empty($idUsuario) || !is_numeric($idUsuario)) {
                    throw new \Exception('PARAMETROS INCORRECTOS (IE)');
                }
                $query->where('id', $idUsuario);
        
                $resultGral["data"] = $query->get(); // ← Respuesta directa

            }

        } catch (\Exception $e) {
            $resultGral['status'] = $resultGral['status'] === 'success' ? 'error' : $resultGral['status'];
            $resultGral['msg'] = $e->getMessage();
            $resultGral['typeCode'] = $e->getCode();
            $resultGral['type'] = ($e->getCode() == 0?'error':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'question'))));
            $resultGral['typeAlert'] = ($e->getCode() == 0?'danger':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'info'))));

            // TO DATATABLE
            $resultGral['recordsFiltered'] = 0;
            $resultGral['recordsTotal'] = 0;
            $resultGral['data'] = [];
        }
        return $resultGral;
    }

    public function usersCreate(Request $request)
    {
        $resultGral = array('status' => 'success', 'totalCount' => 0, 'msg' => 'TODO BIEN', "data" => array());

        DB::beginTransaction();
        try {

            // REGLAS GENERALES
            $rules = [
                'name'     => 'required|string|max:255',
                'email' => 'required|email|string|max:500|unique:users,email',
                'password' => 'required|string|min:6|max:255',
            ];

            $messages = [
                'name.required'     => 'El campo nombre es obligatorio.',
                'name.string'       => 'El nombre debe ser una cadena de texto.',
                'name.max'          => 'El nombre no debe superar los 255 caracteres.',
                
                'email.unique'      => 'El email ingresado no se encuentra disponible.',
                'email.required'    => 'El campo correo electrónico es obligatorio.',
                'email.email'       => 'Debe ingresar un correo electrónico válido.',
                'email.string'      => 'El correo debe ser una cadena.',
                'email.max'         => 'El correo no debe superar los 500 caracteres.',
            
                'password.required' => 'La contraseña es obligatoria.',
                'password.string'   => 'La contraseña debe ser una cadena.',
                'password.min'      => 'La contraseña debe tener al menos 6 caracteres.', // podrías cambiar esto a 6+
                'password.max'      => 'La contraseña no debe superar los 255 caracteres.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                // Aquí sí están disponibles todos los errores
                throw new \Exception("- ".implode("<br>- ", $validator->errors()->all()));
            }

            $validated = $validator->validated(); // SOLO SE ENVIAN LOS VALORES VALIDOS

            $created = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'photo' => '',
                'status' => 1,
                'id_branch' => 0,
                'role' => 'User',
                'last_login' => '',
            ]);

            $resultGral['data'] = $created;

            // throw new \Exception('ERROR DE PRUEBA', 0);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $resultGral['status'] = $resultGral['status'] === 'success' ? 'error' : $resultGral['status'];
            $resultGral['msg'] = $e->getMessage();
            $resultGral['typeCode'] = $e->getCode();
            $resultGral['type'] = ($e->getCode() == 0?'error':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'question'))));
            $resultGral['typeAlert'] = ($e->getCode() == 0?'danger':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'info'))));
        }
        return $resultGral;
    }

    public function usersUpdate(Request $request)
    {
        $resultGral = array('status' => 'success', 'totalCount' => 0, 'msg' => 'TODO BIEN', "data" => array());

        DB::beginTransaction();
        try {

            $idUser = $request->id;

            // BUSCAMOS USUARIO
            $user = User::find($idUser);
            if (!$user) {
                throw new \Exception('Usuario no encontrado', 0);
            }

            // REGLAS GENERALES
            $rules = [
                'name'     => 'required|string|max:255',
                'email' => 'required|email|string|max:500|unique:users,email,' . $user->id,
            ];
            
            if($request->passwordChange == 'S') {
                if (!Hash::check($request->input('passwordUserAnt'), $user->password)) {
                    throw new \Exception("No se logra validar contrase&ntilde;a actual.", 1);
                }

                $rules = array_merge($rules, [
                    'password' => 'required|string|min:6|max:255',
                ]);
            }
            
            $messages = [
                'name.required'     => 'El campo nombre es obligatorio.',
                'name.string'       => 'El nombre debe ser una cadena de texto.',
                'name.max'          => 'El nombre no debe superar los 255 caracteres.',
                
                'email.unique'      => 'El email ingresado no se encuentra disponible.',
                'email.required'    => 'El campo correo electrónico es obligatorio.',
                'email.email'       => 'Debe ingresar un correo electrónico válido.',
                'email.string'      => 'El correo debe ser una cadena.',
                'email.max'         => 'El correo no debe superar los 500 caracteres.',
            
                'password.required' => 'La contraseña es obligatoria.',
                'password.string'   => 'La contraseña debe ser una cadena.',
                'password.min'      => 'La contraseña debe tener al menos 6 caracteres.', // podrías cambiar esto a 6+
                'password.max'      => 'La contraseña no debe superar los 255 caracteres.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                // Aquí sí están disponibles todos los errores
                throw new \Exception("- ".implode("<br>- ", $validator->errors()->all()));
            }

            $validated = $validator->validated(); // SOLO SE ENVIAN LOS VALORES VALIDOS

            // ACTUALIZAMOS
            $updated = $user->update($validated);

            if (!$updated) {
                throw new \Exception('No se logr&oacute; actualizar el usuario.', 0);
            }

            $resultGral['data'] = $updated;

            // throw new \Exception('ERROR DE PRUEBA', 0);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $resultGral['status'] = $resultGral['status'] === 'success' ? 'error' : $resultGral['status'];
            $resultGral['msg'] = $e->getMessage();
            $resultGral['typeCode'] = $e->getCode();
            $resultGral['type'] = ($e->getCode() == 0?'error':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'question'))));
            $resultGral['typeAlert'] = ($e->getCode() == 0?'danger':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'info'))));
        }
        return $resultGral;
    }

    public function usersDeleteActive(Request $request)
    {
        $resultGral = array('status' => 'success', 'totalCount' => 0, 'msg' => 'TODO BIEN', "data" => array());

        DB::beginTransaction();
        try {

            $idUser = $request->id;

            // BUSCAMOS USUARIO
            $user = User::find($idUser);
            if (!$user) {
                throw new \Exception('Usuario no encontrado', 0);
            }

            // REGLAS GENERALES
            $rules = [
                'activo' => 'required|string|in:S,N|max:1',
            ];
            
            $messages = [
                'activo.required'     => 'Parametros incorrectos.',
                'activo.string'       => 'Parametros incorrectos.',
                'activo.max'          => 'Parametros incorrectos.',
                'activo.in'          => 'Parametros incorrectos.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                // Aquí sí están disponibles todos los errores
                throw new \Exception("- ".implode("<br>- ", $validator->errors()->all()));
            }

            $validated = $validator->validated(); // SOLO SE ENVIAN LOS VALORES VALIDOS

            // ACTUALIZAMOS
            $updated = $user->update($validated);

            if (!$updated) {
                throw new \Exception('No se logr&oacute; actualizar el usuario.', 0);
            }

            $resultGral['data'] = $updated;

            // throw new \Exception('ERROR DE PRUEBA', 0);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $resultGral['status'] = $resultGral['status'] === 'success' ? 'error' : $resultGral['status'];
            $resultGral['msg'] = $e->getMessage();
            $resultGral['typeCode'] = $e->getCode();
            $resultGral['type'] = ($e->getCode() == 0?'error':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'question'))));
            $resultGral['typeAlert'] = ($e->getCode() == 0?'danger':($e->getCode() == 1?'info':($e->getCode() == 2?'warning':($e->getCode() == 3?'success':'info'))));
        }
        return $resultGral;
    }
    
}
