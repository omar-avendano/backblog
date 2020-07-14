<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\User;

class UserController extends Controller
{
    //
    public function pruebas(Request $request){
        return "Accion de prubeas User-Controller";
    }

    public function register(Request $request){

        //Recoger datos de usuario por post
        $json=$request->input('json',null);
        $params=json_decode($json);//objeto
        $params_array=json_decode($json,true);//objeto
        //var_dump($params_array);//array
        //die();

        if(!empty($params_array) && !empty($params)){
            //Limpiar datos
            $params_array=array_map('trim',$params_array);

            //Validar datos
            $validate = \Validator::make($params_array,[
                'name'      =>'required|alpha',
                'last_name'   =>'required|alpha',
                'email'   =>'required|email|unique:users', //Comprobar si el usuario existe
                'password'   =>'required'
            ]);
            if($validate->fails()){
                //La validacion ha fallado
                $data= array(
                    'status'=>'error',
                    'code'=>404,
                    'message'=>'El usuario no se ha creado',
                    'errors'=>$validate->errors()
                );

            }else{
                //Validacion pasada correctamente

                //Cifrar la contraseña
                //$pwd=password_hash($params->password, PASSWORD_BCRYPT, ['cost'=>4]);
                $pwd=hash('sha256',$params->password);//un metodo mejor para generar el mismo password


                //Crear el usuario
                $user= new User();
                $user->name=$params_array['name'];
                $user->last_name=$params_array['last_name'];
                $user->email=$params_array['email'];
                $user->password=$pwd;
                $user->role='ROLE_USER';

                //Guardar Usuario
                $user->save();

                $data= array(
                    'status'=>'success',
                    'code'=>200,
                    'message'=>'El usuario se ha creado correctamente',
                    'user'=>$user
                );
            }

        }else{
            $data= array(
                'status'=>'error',
                'code'=>404,
                'message'=>'Los datos enviados no son correctos'
            );
        }


        return response()->json($data,$data['code']);

    }


    public function login(Request $request){

        $jwtAuth= New \JwtAuth();

        //Recibir datos por POST
        $json= $request->input('json',null);
        $params=json_decode($json);
        $params_array=json_decode($json,true);
        //Validar datos
        $validate = \Validator::make($params_array,[
            'email'   =>'required|email', //Comprobar si el usuario existe
            'password'   =>'required'
        ]);
        if($validate->fails()){
            //La validacion ha fallado
            $signup= array(
                'status'=>'error',
                'code'=>404,
                'message'=>'El usuario no se ha podido identificar',
                'errors'=>$validate->errors()
            );

        }else{

            //Cifrar contraseña
            $pwd=hash('sha256',$params->password);
            //Devolver Datos
            $signup=$jwtAuth->signup($params->email,$pwd);

            if(!empty($params->gettoken)){
                $signup=$jwtAuth->signup($params->email,$pwd,true);
            }
        }

        //var_dump($pwd); die();

        return response()->json($signup,200);
    }

    public function update(Request $request){

        //Comprobar si el usuario esta identificado
        $token=$request->header('Authorization');
        $jwtAuth= new \JwtAuth();
        $checkToken=$jwtAuth->checkToken($token);

         //Recoger datos por POST
         $json= $request->input('json',null);
         $params_array=json_decode($json,true);

        if($checkToken && !empty($params_array)){
            //Actualizar Usuario


            //Sacar usuario identificado
            $user=$jwtAuth->checkToken($token,true);

            //Validar los datos
            $validate = \Validator::make($params_array,[
                'name'      =>'required|alpha',
                'last_name'   =>'required|alpha',
                'email'   =>'required|email|unique:users,'.$user->sub
            ]);

            //Quitar los campos que no quiero actualizar
            unset($params_array['id']);
            unset($params_array['role']);
            unset($params_array['password']);
            unset($params_array['created_at']);
            unset($params_array['remember_token']);
            //Actualizar usuario en la DB
            $user_update=User::where('id',$user->sub)->update($params_array);
            //Devolver array con resultado
            $data=array(
                'code'=>200,
                'status'=>'success',
                'user'=>$user,
                'changes'=>$params_array
            );

        }else{
           $data=array(
               'code'=>400,
               'status'=>'error',
               'message'=>'El usuario no esta identificado'
           );
        }

        return response()->json($data,$data['code']);
    }


    public function upload(Request $request){
        //Recoger los datos de la peticion
        $image=$request->file('file0');
        //Validacion de la imagen
        $validate=\Validator::make($request->all(),[
            'file0'=>'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        //Subir y guardar  imagen
        if(!$image || $validate->fails()){
             //Devolver resultado
             $data=array(
                'code'=>400,
                'status'=>'error',
                'message'=>'Error al subir imagen'
            );


        }else{
            $image_name=time().$image->getClientOriginalName();
            \Storage::disk('users')->put($image_name,\File::get($image));

            //Devolver resultado
            $data=array(
                'code'=>200,
                'status'=>'success',
                'image'=>$image_name
            );
        }


        //return response($data,$data['code'])->header('Content-Type','text/plain');
        return response()->json($data,$data['code']);
    }


    public function getImage($filename){

        $isset=\Storage::disk('users')->exists($filename);

        if($isset){
            $file=\Storage::disk('users')->get($filename);
            return new Response($file,200);
        }else{
            $data=array(
                'code'=>404,
                'status'=>'error',
                'message'=>'La imagen no existe'
            );
            return response()->json($data,$data['code']);
        }

    }

    public function detail($id){

        $user=User::find($id);

        if(is_object($user)){
            $data=array(
                'code'=>200,
                'status'=>'success',
                'message'=>$user
            );
        }else{
            $data=array(
                'code'=>404,
                'status'=>'error',
                'message'=>'Usuario no encontrado'
            );
        }

        return response()->json($data,$data['code']);

    }

}
