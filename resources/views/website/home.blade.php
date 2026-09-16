<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Airbagszentrum AI</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <style>
        .card-header {
            background-color: #21ade3;
            color: white;
            font-weight: bold;
            border-radius: 0 !important;
        }

        .card-footer {
            background-color: #21ade3;
            color: white;
            border-radius: 0 !important;
        }

        button.btn.btn-success {
            background-color: maroon;
            width: 100%;
        }

        button.btn.btn-success:active {
            background-color: crimson;
            width: 100%;
        }

        .card-footer {
            padding: 0 !important;
            background: transparent;
        }

        textarea#message-textarea {
            border: none;
        }

        .chat {
            background-color: #eeeeee;
            border: solid 1px;
            border-color: #cccccc;
            border-radius: 0;
            padding: 5px 10px;
            display: inline-block;
        }

        .client {
            background-color: #dddddd;
            border: solid 1px;
            border-color: #cccccc;
            border-radius: 0;
            padding: 5px 10px;
            display: inline-block;
            text-align: right;
        }

        .line-chat {
            display: flex;
            justify-content: flex-start;
            margin: 10px 0;
        }

        .line-client {
            display: flex;
            justify-content: flex-end;
            margin: 10px 0;
        }

        .message {
            font-size: small;
        }

        .card-body {
            overflow-y: scroll;
            height: 370px;
            background: #fafafa;
        }
    </style>
</head>

<body>

    <div class="card rounded-0">
        <div class="card-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat-dots"
                viewBox="0 0 16 16">
                <path
                    d="M5 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0m4 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                <path
                    d="m2.165 15.803.02-.004c1.83-.363 2.948-.842 3.468-1.105A9.06 9.06 0 0 0 8 15c4.418 0 8-3.134 8-7s-3.582-7-8-7-8 3.134-8 7c0 1.76.743 3.37 1.97 4.6a10.437 10.437 0 0 1-.524 2.318l-.003.011a10.722 10.722 0 0 1-.244.637c-.079.186.074.394.273.362a21.673 21.673 0 0 0 .693-.125zm.8-3.108a1 1 0 0 0-.287-.801C1.618 10.83 1 9.468 1 8c0-3.192 3.004-6 7-6s7 2.808 7 6c0 3.193-3.004 6-7 6a8.06 8.06 0 0 1-2.088-.272 1 1 0 0 0-.711.074c-.387.196-1.24.57-2.634.893a10.97 10.97 0 0 0 .398-2" />
            </svg> Chat online
        </div>
        <div>
<div class="card-body" id="chat-container"></div>
        <div class="card-footer">
            <textarea class="form-control" id="message-textarea" placeholder="Escreva uma mensagem"></textarea>
        </div>
        </div>
        <!-- Modal Condições de Utilização -->
        <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true" style="margin-top: 44px;">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                <div class="modal-header bg-warning">
                    <p id="termsModalLabel">Condições de utilização de Chat</p>
                </div>
                <div class="modal-body">
                    <p><a href="https://techniczentrum.com/pt/content/35-sobre-a-utilizacao-do-nosso-chat-de-apoio" target="_new">Condições de utilização</a></p>
                    <p>
                    Se concordar, clique em "Aceito" para continuar.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" id="acceptTerms" class="btn btn-primary">Aceito</button>
                </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js">
    </script>
    <script>window.publicChatAssistant = @json($assistant->id);</script>
    <script src="{{ asset('js/public-chat.js') }}?v={{ filemtime(public_path('js/public-chat.js')) }}" defer></script>
</body>
</html>