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
        <div class="card-body" id="chat-container"></div>
        <div class="card-footer">
            <textarea class="form-control" id="message-textarea" placeholder="Escreva uma mensagem"></textarea>
        </div>
    </div>

    <!-- Modal Condições de Utilização -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header bg-warning">
            <h5 class="modal-title" id="termsModalLabel">Condições de Utilização do Chat</h5>
        </div>
        <div class="modal-body">
            <p>
            Este chat é controlado por uma Inteligência Artificial (IA) e destina-se a auxiliar com informações gerais.
            Apesar de todos os esforços para garantir a precisão, as respostas podem conter erros ou imprecisões.
            </p>
            <p>
            Ao utilizar este chat, concorda que as informações fornecidas não substituem aconselhamento profissional e
            que podem ser sujeitas a falhas técnicas ou interpretações incorretas.
            </p>
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


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js">
    </script>
    <script>
        const timeout = 3000;
        const first_message = 'Em que posso ajudar?';
        const waiting_message = 'Não saia desta página nem clique em outros links enquanto procuro. Pode demorar um pouco.';
        const email_waiting_message = 'Peço que aguarde enquanto envio o pedido.';
        const chat_container = $('#chat-container');
        const assistant_id = {{ $assistant-> id }};
        const message_textarea = $('#message-textarea');
        const project = '{{ $assistant->project->name }}';
        var thread_id = null;
        var run_id = null;
        var log_id = null;
        $(() => {
            setTimeout(() => {
                checkIfThreadInSession().then((resp) => {
                    if (resp !== 'undefined') {
                        thread_id = resp;
                        console.log(thread_id);
                        checkIfLogInSession().then((resp) => {
                            if (resp) {
                                log_id = resp;
                                getAllMessages(log_id).then((resp) => {
                                    resp.forEach(element => {
                                        addMessageToContent(element.role, element.message);
                                    });
                                });
                            } else {
                                //addMessageToContent('chat', first_message);
                            }
                        });
                    } else {
                        //addMessageToContent('chat', first_message);
                    }
                });
            }, timeout);
            $('#message-textarea').keypress(function (e) {
                if (e.which == 13) {
                    sendMessage();
                    return false;
                }
            });
        });
        sendMessage = () => {
            let message = message_textarea.val();
            if (message.length > 0) {
                $('#message-textarea').attr('disabled', true);
                addMessageToLog('user', message).then((resp) => {
                    log_id = resp.log_id;
                    storeLogInSession(log_id);
                });
                addMessageToContent('user', message);
                message_textarea.val('');
                message_textarea.LoadingOverlay('show');
                if (!thread_id) {
                    createThreadAndRun(message).then((resp) => {
                        thread_id = resp.thread_id;
                        run_id = resp.id;
                        storeThreadInSession(thread_id).then((resp) => {
                            let interval = setInterval(() => {
                                listRunSteps(thread_id, run_id).then((resp) => {
                                    let has_more = resp.has_more;
                                    getRunStatus(thread_id, run_id).then((resp) => {
                                        let status = resp.status;
                                        if (status == 'completed' && has_more == false) {
                                            clearInterval(interval);
                                            getMessages(thread_id).then((resp) => {
                                                message = resp.data[0].content[0].text.value;
                                                addMessageToLog('chat', message).then((resp) => {
                                                    log_id = resp.log_id;
                                                    storeLogInSession(log_id);
                                                });
                                                addMessageToContent('chat', message);
                                                message_textarea.LoadingOverlay('hide');
                                                $('#message-textarea').attr('disabled', false);
                                                $('#message-textarea').focus();
                                            });
                                        }
                                    });
                                });
                            }, 5000);
                        })
                    });
                } else {
                    addMessage(thread_id, message).then((resp) => {
                        console.log(resp);
                        runTheThread(thread_id).then((resp) => {
                            let run_id = resp.id;
                            let interval = setInterval(() => {
                                listRunSteps(thread_id, run_id).then((resp) => {
                                    let type = resp.data[0].type;
                                    if (type == 'message_creation') {
                                        let has_more = resp.has_more;
                                        getRunStatus(thread_id, run_id).then((resp) => {
                                            let status = resp.status;
                                            if (status == 'completed' && has_more == false) {
                                                clearInterval(interval);
                                                getMessages(thread_id).then((resp) => {
                                                    message = resp.data[0].content[0].text.value;
                                                    addMessageToLog('chat', message).then((resp) => {
                                                        log_id = resp.log_id;
                                                        storeLogInSession(log_id);
                                                    });
                                                    addMessageToContent('chat', message);
                                                    message_textarea.LoadingOverlay('hide');
                                                    $('#message-textarea').attr('disabled', false);
                                                    $('#message-textarea').focus();
                                                });
                                            }
                                        });
                                    } else if (type == 'tool_calls') {
                                        let function_name = resp.data[0].step_details.tool_calls[0].function.name;
                                        let data = JSON.parse(resp.data[0].step_details.tool_calls[0].function.arguments);
                                        let tool_call_id = resp.data[0].step_details.tool_calls[0].id;
                                        if (function_name == 'get_products') {
                                            if (data.symbol) {
                                                clearInterval(interval);
                                                addMessageToContent('chat', waiting_message);
                                                getProducts(data.symbol).then((resp) => {
                                                    let output = JSON.stringify(resp);
                                                    if (output == '[]') {
                                                        output = 'Não existe em stock.';
                                                    }
                                                    submitToolOutputsToRun(thread_id, run_id, tool_call_id, output).then((resp) => {
                                                        let run_id = resp.id;
                                                        let interval_function = setInterval(() => {
                                                            getRunStatus(thread_id, run_id).then((resp) => {
                                                                status = resp.status;
                                                                if (status == 'completed') {
                                                                    clearInterval(interval_function);
                                                                    getMessages(thread_id).then((resp) => {
                                                                        message = resp.data[0].content[0].text.value;
                                                                        addMessageToContent('chat', message);
                                                                        message_textarea.LoadingOverlay('hide');
                                                                        $('#message-textarea').attr('disabled', false);
                                                                        $('#message-textarea').focus();
                                                                    });
                                                                }
                                                            });
                                                        }, 5000);
                                                    });
                                                });
                                            }
                                        }
                                        if (function_name == 'send_email') {
                                            if (data.data) {
                                                addMessageToContent('chat', email_waiting_message);
                                                clearInterval(interval);
                                                sendEmail(data).then(() => {
                                                    submitToolOutputsToRun(thread_id, run_id, tool_call_id, true).then((resp) => {
                                                        let run_id = resp.id;
                                                        let interval_function2 = setInterval(() => {
                                                            getRunStatus(thread_id, run_id).then((resp) => {
                                                                status = resp.status;
                                                                if (status == 'completed') {
                                                                    clearInterval(interval_function2);
                                                                    getMessages(thread_id).then((resp) => {
                                                                        message = resp.data[0].content[0].text.value;
                                                                        addMessageToContent('chat', message);
                                                                        message_textarea.LoadingOverlay('hide');
                                                                        $('#message-textarea').attr('disabled', false);
                                                                        $('#message-textarea').focus();
                                                                    });
                                                                }
                                                            });
                                                        }, 5000);
                                                    });
                                                });
                                            }
                                        }
                                    } else {
                                        clearInterval(interval);
                                        message_textarea.LoadingOverlay('hide');
                                        $('#message-textarea').attr('disabled', false);
                                        $('#message-textarea').focus();
                                    }
                                });
                            }, 5000);
                        });
                    });
                }
            }
        }
        submitToolOutputsToRun = (thread_id, run_id, tool_call_id, output) => {
            let data = {
                thread_id: thread_id,
                run_id: run_id,
                tool_call_id: tool_call_id,
                output: output
            }
            return new Promise((resolve, reject) => {
                $.post({
                    url: '/chat/submit-tool-outputs-to-run',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: data,
                    success: (resp) => {
                        resolve(resp);
                    },
                    error: (err) => {
                        reject(err);
                    }
                });
            });
        }
        runTheThread = async (thread_id) => {
            return $.get('/chat/run-the-thread/' + assistant_id + '/' + thread_id);
        }
        addMessage = async (thread_id, message) => {
            let data = {
                thread_id: thread_id,
                message: message
            }
            return new Promise((resolve, reject) => {
                $.post({
                    url: '/chat/add-message',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: data,
                    success: (resp) => {
                        resolve(resp);
                    },
                    error: (err) => {
                        reject(err);
                    }
                });
            });
        }
        getMessages = async (thread_id) => {
            return $.get('/chat/get-messages/' + thread_id);
        }
        createThreadAndRun = async (message) => {
            try {
                let data = {
                    assistant_id: assistant_id,
                    message: message
                };
                return new Promise((resolve, reject) => {
                    $.post({
                        url: '/chat/create-thread-and-run',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: data,
                        success: (resp) => {
                            resolve(resp);
                        },
                        error: (jqXHR, textStatus, errorThrown) => {
                            reject(errorThrown);
                        }
                    });
                });
            } catch (err) {
                console.error(err);
            }
        };
        listRunSteps = async (thread_id, run_id) => {
            return $.get('/chat/list-run-steps/' + thread_id + '/' + run_id);
        }
        getRunStatus = async (thread_id, run_id) => {
            return $.get('/chat/get-run-status/' + thread_id + '/' + run_id);
        }
        listMessages = async () => {
            try {
                const messages = await $.get('/chat/list-messages/' + assistant_id + '/' + thread_id);
                return messages
            } catch (error) {
                return error;
            }
        }
        getProducts = async (search) => {
            try {
                return await $.get('/api/search/' + assistant_id + '/' + search);
            } catch (error) {
                return error;
            }
        }
        sendEmail = async (data) => {
            try {
                return $.post({
                    url: '/api/send-email',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: data
                });
            } catch (error) {
                console.log(error);
            }
        }
        addMessageToContent = (role, message) => {
            switch (role) {
                case 'chat':
                    html = '<div class="line-chat">';
                    html += '<div class="chat">';
                    html += '<div class="message">';
                    html += '<small>Zentrum</small><br>';
                    html += message;
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    break;
                default:
                    html = '<div class="line-client">';
                    html += '<div class="client">';
                    html += '<div class="message">';
                    html += '<small>Eu</small><br>';
                    html += message;
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    break;
            }
            chat_container.append(html);
            chat_container.scrollTop(chat_container[0].scrollHeight);
        }
        addMessageToLog = async (role, message) => {
            let data = {
                project: project,
                log_id: log_id,
                role: role,
                message: message
            }
            try {
                return $.post({
                    url: '/chat/log',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: data
                });
            } catch (error) {
                console.log(error);
            }
        };
        storeThreadInSession = async (thread_id) => {
            try {
                return $.get('/chat/store-thread-in-session/' + thread_id);
            } catch (error) {
                console.log(error);
            }
        }
        storeLogInSession = async (log_id) => {
            try {
                return $.get('/chat/store-log-in-session/' + log_id);
            } catch (error) {
                console.log(error);
            }
        }
        checkIfThreadInSession = async () => {
            try {
                return $.get('/chat/check-if-thread-in-session');
            } catch (error) {
                console.log(error);
            }
        }
        checkIfLogInSession = async () => {
            try {
                return $.get('/chat/check-if-log-in-session');
            } catch (error) {
                console.log(error);
            }
        }
        getAllMessages = async (log_id) => {
            try {
                return $.get('/chat/get-all-messages/' + log_id);
            } catch (error) {
                console.log(error);
            }
        }
        $(() => {
            // Verifica se já aceitou os termos
            if (!localStorage.getItem('chat_terms_accepted')) {
                $('#termsModal').modal({
                    backdrop: 'static',
                    keyboard: false
                }).modal('show');
            }

            $('#acceptTerms').on('click', function() {
                localStorage.setItem('chat_terms_accepted', 'true');
                $('#termsModal').modal('hide');
            });

            // Bloqueia envio de mensagens se não aceitou
            $('#message-textarea').on('keypress', function(e) {
                if (!localStorage.getItem('chat_terms_accepted')) {
                    e.preventDefault();
                    $('#termsModal').modal('show');
                }
            });
        });

    </script>

</body>

</html>