## Produção AWS — 2026-09-16

O atendimento telefónico corre integralmente na AWS, sem túnel SSH nem PC ligado.
Serviço systemd: `aizentrum-voice` (enabled, restart automático).
Código: `/opt/aizentrum-voice`; instruções: `/etc/aizentrum-voice/instructions.txt`.
Credenciais: `/etc/aizentrum-voice/voice.env`, acesso limitado a root e ao serviço.
Node: `/usr/local/bin/node`. Portas internas: 9019 AudioSocket, 9020 health.
Trunk: 13.38.206.137:5060 UDP, alaw, origem autorizada 194.38.128.79.
A unidade está em `deployment/asterisk/aizentrum-voice.service`.
Alterações no editor local não sincronizam automaticamente para produção.
Deploy de voz independente do deploy Laravel; não é atualizado pelo push main.
Não guarda nem envia reclamações, gravações ou transcrições.
Validado: serviço ativo, API aceite, áudio bidirecional em chamada interna.
Pendente: validação auditiva por chamada real depois desta migração.
Rollback: parar `aizentrum-voice` na AWS e repor o túnel para o serviço local.
As secções seguintes descrevem o piloto local e o estado histórico de 9 de setembro.

# Piloto de voz — Techniczentrum / Electriczentrum

O Asterisk corre no Lightsail. Este serviço Node.js corre no computador e usa
a API OpenAI Realtime: o modelo não é executado offline no computador.
O Laravel mantém a função de painel; não processa diretamente o áudio.

## Estado verificado em 2026-09-09

- Servidor: `zentrum-platform-prod-01`, Paris, `13.38.206.137`.
- Ubuntu 24.04.4, 8 vCPU, 32 GB; cerca de 19 GiB disponíveis na inspeção.
- Asterisk 20.6 instalado pelo repositório Ubuntu e ativo no systemd.
- PJSIP ligado apenas a `127.0.0.1:5060`; sem trunk público ativo.
- Configuração anterior em `/etc/asterisk.before-aizentrum-20260909`.
- AudioSocket: AWS `127.0.0.1:9019` → túnel SSH → PC `127.0.0.1:9019`.
- Serviço de voz: `http://127.0.0.1:9020/health`.
- Laravel: `http://127.0.0.1:8000/login`, HTTP 200 verificado.
- Duas sessões Realtime aceites; áudio enviado e recebido num teste interno
  com `Local/700@ai-local-test/n` e áudio de demonstração do Asterisk.
- MySQL local: ativo em `127.0.0.1:3306`. Ligação do Laravel à base
  `aizentrum_sandbox` e leitura da tabela de utilizadores verificadas.
  Login com a palavra-passe do utilizador ainda não verificado.

## Executar localmente

### Teste no browser

No painel local, abrir `/admin/voice-test` (menu **Testar atendimento**).
Depois de iniciar e permitir o microfone, a conversa usa WebRTC com a OpenAI,
através de uma negociação autenticada pelo Laravel. Este percurso não depende
do serviço AudioSocket ou do túnel SSH. A chave da API permanece no servidor.
O botão Terminar desliga o microfone e a sessão; o teste termina também ao sair
da página ou após dez minutos. A transcrição é temporária e não é guardada.
As rotas só funcionam em `APP_ENV=local` e exigem login.

### Serviço AudioSocket para Asterisk

Requer Node.js 24, PHP e OpenSSH. A chave OpenAI existente é lida do `.env`
do Laravel; não colocar credenciais no código ou nos argumentos da linha de comandos.

Na pasta `voice-service`:

```powershell
npm ci --ignore-scripts
npm start
```

O padrão é `VOICE_MODE=realtime`, modelo `gpt-realtime`.
`VOICE_MODE=echo` permite testes de eco sem usar a OpenAI.
`OPENAI_REALTIME_MODEL` permite configurar o modelo.

Noutro terminal, abrir o túnel com a chave SSH existente da região Paris:

```powershell
ssh -N -o BatchMode=yes -o StrictHostKeyChecking=yes -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -i "$env:USERPROFILE/Downloads/LightsailDefaultKey-eu-west-3.pem" -R 127.0.0.1:9019:127.0.0.1:9019 ubuntu@13.38.206.137
```

Na raiz do projeto:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

O computador e o túnel têm de ficar ligados. Os processos iniciados nesta sessão
não têm arranque automático após reiniciar o Windows. Logs da sessão estão em
`$env:TEMP/aizentrum-{voice,tunnel,laravel}*.log`.

## Teste interno

No servidor, sem efetuar chamadas para a rede telefónica:

```sh
sudo asterisk -rx 'channel originate Local/700@ai-local-test/n application Wait 10'
```

Este teste cria uma sessão paga curta na API, quando o serviço está em modo
Realtime. Consultar `/health`: `sessionsReady` e `sentBytes` devem aumentar.
Para verificar entrada de áudio, usar `Playback demo-congrats` em vez de `Wait 10`
e desligar o canal de teste após alguns segundos. `receivedBytes` deve aumentar.

## Antes de chamadas reais

1. O fornecedor tem de converter a conta de softphone para trunk de servidor.
2. Confirmar proxy redundante: `voip2.voipounify.com` devolveu NXDOMAIN.
   O principal `voip.voipunify.com` resolveu para `194.38.128.79`.
3. Obter os IPs/ranges de sinalização e media, transporte/porta e requisitos de
   registo, autenticação e formato do número recebido.
4. Configurar trunk PJSIP com G.711 aLaw e credenciais num ficheiro protegido,
   fora do Git. Configurar NAT com IP público `13.38.206.137` e rede privada.
5. Abrir apenas as portas/ranges necessários no firewall Lightsail e UFW,
   limitados aos IPs confirmados pelo operador. Nenhuma regra foi aberta nesta fase.
6. Testar chamada real, áudio em ambos os sentidos, desligar, perda do túnel,
   duas chamadas concorrentes e rejeição da terceira.

## Limites deste protótipo

Atualmente é um teste de conversação, com instruções mínimas. Não guarda
reclamações, gravações ou transcrições, nem possui integração de negócio no Laravel.
Aceita no máximo duas ligações, com limite de dez minutos por sessão, filas de
áudio limitadas e reprodução de PCM em blocos de 20 ms. A interrupção da fala
limpa áudio pendente e informa a API do trecho reproduzido. Qualidade perceptiva,
latência e interrupção ainda devem ser validadas numa chamada humana.

AudioSocket usa PCM mono 8 kHz; a ligação à OpenAI usa G.711 µ-law 8 kHz,
com conversão local pelo pacote `alawmulaw`. O trunk do operador continuará
a usar aLaw, convertido pelo Asterisk.

Documentação:
- https://docs.asterisk.org/Configuration/Channel-Drivers/AudioSocket/
- https://developers.openai.com/api/docs/guides/realtime-websocket
- https://developers.openai.com/api/docs/guides/realtime-conversations

## Trunk ativado em 09/09/2026

Após confirmação do utilizador de que o fornecedor registou o IP:
- Endpoint VoIP Unify por identificação de IP: 194.38.128.79/32, UDP 5060.
- Teste OPTIONS: contacto Avail, RTT aproximadamente 35 ms.
- PJSIP agora escuta em 0.0.0.0:5060; NAT público 13.38.206.137.
- UFW e Lightsail permitem UDP 5060 e RTP 10000–10099 apenas daquele IP.
- Sem REGISTER ou chamadas de saída configurados; modo de entrada por IP.
- Rotas de entrada: 300405272, 351300405272, +351300405272 e airbagstst.
- Serviço AudioSocket atualizado para Cedar, semantic_vad low e instruções
  guardadas no painel, lidas novamente no início de cada chamada.
- Backup anterior ao trunk: /etc/asterisk.before-trunk-20260909.
- Pendente: chamada real e confirmação do IP de media no SDP. Se o operador
  usar outro IP de RTP, confirmar esse endereço antes de ampliar o firewall.
- Proxy redundante continua por confirmar. As notas anteriores sobre trunk
  desativado e portas fechadas descrevem o estado inicial e ficam superadas aqui.
