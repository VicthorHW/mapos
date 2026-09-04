# Credencial de aparelho nas Ordens de Servico

Esta customizacao adiciona senha/PIN e padrao de desenho 3x3 a 6x6 as OS do
MAP-OS. O segredo e criptografado no banco, revelado sob demanda somente no
painel interno e removido das consultas, views e APIs da area do cliente.

## Instalacao nova ou atualizacao deste fork

O caminho recomendado e sempre instalar o MAP-OS a partir deste fork. Assim,
o codigo da feature acompanha o restante da aplicacao e o instalador precisa
apenas criar a chave, executar a migration e validar a integracao.

Antes da primeira execucao, configure normalmente o `application/.env` e
tenha um backup atual do banco.

Windows/XAMPP:

```powershell
.\tools\device-credential\install.ps1
```

Tambem e possivel executar `tools\device-credential\install.cmd`.

Linux:

```sh
sh tools/device-credential/install.sh
```

Para uma instalacao nova, clone diretamente a sua fork e selecione a branch ou
tag que contem a feature. Depois conclua a configuracao normal do MAP-OS e rode
o instalador:

```sh
git clone <URL-DA-SUA-FORK> mapos
cd mapos
git checkout <BRANCH-OU-TAG-DA-SUA-FORK>
php tools/device-credential/install.php
```

Nao e necessario aplicar a feature no repositorio oficial nem fazer um
`cherry-pick` a cada instalacao: o codigo passa a fazer parte permanente da sua
fork.

Execucao direta e opcoes:

```sh
php tools/device-credential/install.php
php tools/device-credential/install.php --verify-only
php tools/device-credential/install.php --skip-migration
php tools/device-credential/install.php --skip-key
php tools/device-credential/install.php --skip-tests
```

O instalador e idempotente: nao troca uma chave ja configurada e so adiciona
colunas ausentes. Alem da migration normal, ele executa um verificador de
schema independente da ordem cronologica das migrations. Nunca substitua
`DEVICE_CREDENTIAL_KEY` em uma
base que ja possua credenciais; isso tornaria os registros anteriores
ilegíveis. Guarde essa chave no backup seguro da instalacao, separada do banco.

## Verificacao manual apos instalar

1. Crie uma OS com senha/PIN e confirme que o cadastro exige a credencial.
2. Crie outra OS com um padrao e teste grades 3x3 e 6x6.
3. Abra a visualizacao interna, revele e reproduza o padrao.
4. Imprima a via normal e confirme que ela nao contem a credencial.
5. Imprima `Via tecnica` e confirme texto, sequencia e diagrama.
6. Entre no portal do cliente e confira lista, detalhes e impressao.
7. Se a API estiver ativa, confira que as respostas comuns nao possuem chaves
   cujo nome comece com `credencial_`.

## Atualizando para uma nova versao do MAP-OS

Mantenha a feature versionada permanentemente nesta fork. Para incorporar uma
nova versao publicada pelo upstream do MAP-OS:

1. Crie uma branch temporaria a partir da branch atual da sua fork.
2. Busque o upstream e faca merge da nova versao nessa branch. A feature ja
   estara presente e nao precisa ser reaplicada.
3. Resolva eventuais conflitos sem aceitar novamente `os.*` ou `clientes.*` no
   `Conecte_model`.
4. Execute:

   ```sh
   php tools/device-credential/install.php --verify-only
   composer test
   ```

5. Execute o instalador completo e o roteiro manual acima em uma copia local ou
   ambiente de teste. Isso valida o funcionamento antes de alterar a producao;
   nao tem relacao com integrar a feature ao repositorio oficial.
6. Faca backup, incorpore a branch testada na branch principal da sua fork e
   atualize a producao.

O modo `--verify-only` falha deliberadamente quando um arquivo ou ponto de
integracao desaparece. Isso transforma mudancas estruturais do upstream em um
conflito visivel, em vez de permitir que a feature pare de funcionar ou que um
campo sensivel seja exposto silenciosamente.

O arquivo `tools/device-credential/manifest.json` registra a versao da feature,
as versoes do MAP-OS ja testadas e a separacao entre arquivos exclusivos e
pontos de integracao. Em uma versao ainda nao testada, o instalador emite um
aviso, mas continua com as verificacoes estruturais.

### Pontos que normalmente exigem revisao em conflitos

- `application/controllers/Os.php`: cadastro, edicao, revelacao e impressao.
- `application/controllers/api/v1/OsController.php`: validacao e limpeza das respostas.
- `application/models/Conecte_model.php`: lista explicita de colunas publicas.
- `application/controllers/Mine.php` e API do cliente: uso do model publico.
- Views internas de adicionar, editar, visualizar e imprimir OS.
- `banco.sql` e a migration da feature.

As views `application/views/conecte/*` nao devem receber nem renderizar dados
da credencial. Emails e mensagens de WhatsApp tambem devem continuar sem esse
conteudo.
