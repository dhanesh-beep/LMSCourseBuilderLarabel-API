<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;

class ResponseController extends Controller
{

    //HTTP status codes
    static $HTTP_NOT_FOUND = 404;
    static $HTTP_OK = 200;
    static $HTTP_UNPROCESSABLE_ENTITY = 422;
    static $HTTP_UNAUTHORIZED = 401;
    static $HTTP_BAD_REQUEST = 400;
    static $HTTP_FORBIDDEN = 403;
    static $HTTP_CONFLICT = 409;


    /**
     * Returns a json when data is not found
     *
     * @param [string] $message
     * @param [array] $errors
     * @return json
     */
    public function notFound($message, $errors = null)
    {
        $error_message = is_null($errors) ? 'not specified' : $errors;
        $info = [
            "status" => self::$HTTP_NOT_FOUND,
            "response" => self::$HTTP_NOT_FOUND,
            'message' => $message,
            'errors' => $error_message,
        ];
        return Response::json($info, self::$HTTP_NOT_FOUND);
    }


    /**
     * Executes and returns well formatted json of errors
     * that occured during validation
     *
     * @param [string] $message
     * @param [collection] $errors
     * @return json
     */
    public function validationFailed($message, $errors)
    {

        $info = [
            "status" =>  self::$HTTP_UNPROCESSABLE_ENTITY,
            "response" => self::$HTTP_UNPROCESSABLE_ENTITY,
            'message' => $message,
            'errors' => $errors,
        ];
        return Response::json($info, self::$HTTP_UNPROCESSABLE_ENTITY);
    }


    /**
     * Returns json stating why a request is unauthorized
     * @param [string] $message
     * @return json
     */
    public function unauthorized($message)
    {
        $info = [
            "status" => self::$HTTP_UNAUTHORIZED,
            "response" => self::$HTTP_UNAUTHORIZED,
            'message' => $message,
            'errors' => [$message]
        ];
        return Response::json($info, self::$HTTP_UNAUTHORIZED);
    }

    /**
     * Returns json stating why data creation failed
     * @param [string] $message
     * @return json
     */
    public function actionSuccess($message, $data = null)
    {
        $info = [
            "status" => self::$HTTP_OK,
            "response" => self::$HTTP_OK,
            'message' => $message,
        ];
        if ($data != null) {
            $info['data'] = $data;
        }
        return Response::json($info, self::$HTTP_OK);
    }

    /**
     * Returns json stating why data creation failed
     * @param [string] $message
     * @return json
     */
    public function actionFailure($message, $data = null)
    {
        $info = [
            "status" => self::$HTTP_CONFLICT,
            "response" => self::$HTTP_CONFLICT,
            'message' => $message,
        ];
        if ($data != null) {
            $info['data'] = $data;
        }
        return Response::json($info, self::$HTTP_CONFLICT);
    }


    /**
     * Returns json
     * @param [string] $message
     * @return json
     */
    public function badRequest($message)
    {
        $info = [
            "status" =>  self::$HTTP_BAD_REQUEST,
            "response" => self::$HTTP_BAD_REQUEST,
            'message' => $message,
        ];
        return Response::json($info, self::$HTTP_FORBIDDEN);
    }

        /**
     * Returns json
     * @param [string] $message
     * @return json
     */
    public function forbidden($message)
    {
        $info = [
            "status" =>  self::$HTTP_FORBIDDEN,
            "response" => self::$HTTP_FORBIDDEN,
            'message' => $message,
        ];
        return Response::json($info, self::$HTTP_FORBIDDEN);
    }

    public function customizingResponseData($list): array
    {
        return [
            // 'data' => $list->items(),
            // 'pagination' => [
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'per_page' => $list->perPage(),
                'total' => $list->total(),
                'from' => $list->firstItem(),
                'to' => $list->lastItem(),
                'has_more_pages' => $list->hasMorePages(),
            // ],
        ];
    }

}
