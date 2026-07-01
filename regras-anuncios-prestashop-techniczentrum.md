# Regras de anuncios PrestaShop TechnicZentrum

Versao: 2026-07-01

## Objetivo

Gerar anuncios completos, consistentes e orientados a SEO para produtos TechnicZentrum no site www.techniczentrum.com, a partir de dados importados do ZCManager e enriquecidos no Zentrum AI.

O dominio principal e pecas e componentes automoveis. Quando a peca for de veiculos hibridos ou eletricos, usar terminologia especifica desse universo. Para restantes pecas, usar terminologia automovel tecnica e neutra.

## Idiomas obrigatorios

Todos os rascunhos devem conter conteudo em:

- PT
- FR
- ES
- EN

As referencias, codigos, marcas, modelos, medidas e identificadores tecnicos nunca devem ser traduzidos, reordenados ou alterados.

## Fontes e seguranca

- Usar apenas dados existentes no anuncio, pesquisa interna, pesquisa web ja guardada, analise IA, imagens e categorias PrestaShop disponiveis.
- Nunca inventar dados tecnicos, referencias, anos, compatibilidades, aplicacoes, caracteristicas ou estado fisico.
- Se o ano do veiculo nao existir nos dados, omitir o ano.
- Se o tipo de componente nao for seguro, usar o melhor termo generico inferivel e adicionar aviso para validacao humana.
- Se a categoria for incerta, sugerir a mais provavel e indicar o motivo.
- Nao usar linguagem promocional vazia nem promessas absolutas.

## Estrutura do produto

Cada anuncio deve ter:

- Nome curto e tecnico.
- Resumo comercial curto.
- Descricao completa clara e verificavel.
- Meta title.
- Meta description.
- Slug PrestaShop.
- Tags/keywords.
- Categoria PrestaShop sugerida.
- Marca e modelo quando existirem.
- Referencia principal e referencias adicionais quando existirem.
- Avisos de validacao quando houver dados em falta ou incerteza.

## Regras de nome

O nome deve seguir, sempre que possivel:

`Tipo de componente + Marca + Modelo + Referencia`

Exemplos:

- `Modulo ABS Mercedes Classe C W204 A2045455132`
- `Inversor Mercedes W205 A0009000000`
- `Centralina Airbag Peugeot Boxer 30724045`

Nao incluir ano se nao tiver sido fornecido.
Nao repetir a mesma referencia varias vezes.
Nao acrescentar compatibilidades nao confirmadas.

## Resumo e descricao

O resumo deve ser curto, comercial e tecnico.

A descricao deve:

- Identificar a peca.
- Indicar a referencia principal.
- Mencionar marca/modelo apenas se existirem nos dados.
- Pedir confirmacao de compatibilidade pela referencia.
- Evitar afirmacoes sobre estado, garantia, programacao, codificacao ou compatibilidade se nao estiverem nos dados.

## SEO

- Meta title ate 70 caracteres.
- Meta description ate 160 caracteres.
- Slug em minusculas, sem acentos, com hifens.
- Tags devem incluir referencia, tipo de peca, marca e modelo quando existirem.

## Categorias e filtros

- Escolher a melhor categoria a partir das categorias reais PrestaShop disponiveis.
- Preferir categorias especificas a categorias genericas quando o score for razoavel.
- Guardar a razao da escolha.
- Mapear marca e modelo para filtros quando existirem nos dados.

## Imagens

- Usar imagens finais ja selecionadas no pipeline.
- Imagens geradas por IA devem preservar a forma observavel da peca e remover referencias visiveis.
- Nao reutilizar imagens de terceiros como imagem final sem transformacao/validacao.
- Se uma imagem for apenas ilustrativa, indicar isso nas notas de validacao.
