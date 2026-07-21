<?php

namespace App\Http\Controllers\Traits;

trait Iftech
{
    public function login()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://zcmanager.com/api/auth/login2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('email' => env('IFTECH_LOGIN'), 'password' => env('IFTECH_PASSWORD')),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;

    }

    public function sendForm($access_token, $data)
    {

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://zcmanager.com/api/forms',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
    }

    public function zcmCategories()
    {

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://zcmanager.com/api/orders/categories',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }

    public function zcmSubCategories($access_token, $phase_id)
    {

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://zcmanager.com/api/orders/categories/' . $phase_id . '/subCategories',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $access_token
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, true);
    }

    public function followup($access_token, $id, $phase_id, $status_id, $obs, $email)
    {

        $curl = curl_init();

        $data = array(
            "id" => $id,
            "phase_id" => $phase_id,
            "status_id" => $status_id,
            "obs" => $obs,
            "email" => $email
        );

        $jsonData = json_encode($data);

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://zcmanager.com/api/orders/followup',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $jsonData,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
    }

    public function checkStock($access_token, $prestashop_id)
    {

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://zcmanager.com/api/products/stocks/prestashop/' . $prestashop_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $access_token
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }

    public function createStock($access_token, $request)
    {

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://zcmanager.com/api/products/stocks',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{
                    "id_product": "' . $request->prestashop_id . '",
                    "category": "' . $request->category . '",
                    "brand_reference": "' . $request->brand_reference . '",
                    "name": "' . $request->name . '",
                    "manufacturer": "' . $request->manufacturer . '",
                    "manufacturer_reference": "' . $request->manufacturer_reference . '",
                    "other_references": "' . $request->other_references . '",
                    "car_model": "' . $request->car_model . '",
                    "stock": "' . $request->stock . '",
                    "stock_location": "' . $request->stock_location . '",
                    "observations": "' . $request->observations . '",
                    "email": "' . $request->email . '",
                    "price": "' . $request->price . '"
                }',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response);
    }

    public function updateStock($access_token, $request)
    {
        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://zcmanager.com/api/products/stocks/' . $request->stock_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => '{
                    "id_product": "' . $request->prestashop_id . '",
                    "category": "' . $request->category . '",
                    "brand_reference": "' . $request->brand_reference . '",
                    "name": "' . $request->name . '",
                    "manufacturer": "' . $request->manufacturer . '",
                    "manufacturer_reference": "' . $request->manufacturer_reference . '",
                    "other_references": "' . $request->other_references . '",
                    "car_model": "' . $request->car_model . '",
                    "stock": "' . $request->stock . '",
                    "stock_location": "' . $request->stock_location . '",
                    "observations": "' . $request->observations . '",
                    "email": "' . $request->email . '",
                    "price": "' . $request->price . '"
        }',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response);
    }

    public function zcmOrders($access_token, $company, $start_date, $end_date)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://zcmanager.com/api/orders/date',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('company' => $company, 'start_date' => $start_date, 'end_date' => $end_date),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $access_token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, true);
    }
}
