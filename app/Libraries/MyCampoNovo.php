<?php

namespace App\Libraries;

use App\Models\Config\ConfigDicDadosModel;

/**
 * MyCampo — Biblioteca de Campos de Formulário
 *
 * Responsável por gerar campos HTML de formulário de forma padronizada,
 * carregando metadados do dicionário de dados quando tabela e campo são informados.
 *
 * Uso básico:
 *   $campo = new MyCampo('tabela', 'nome_campo');
 *   $campo->setLabel('Nome')->setObrigatorio()->setValor($valor);
 *   echo $campo->crInput();
 *
 * Uso sem banco:
 *   $campo = new MyCampo();
 *   $campo->objeto = 'input';
 *   $campo->tipo   = 'text';
 *   $campo->nome   = 'meu_campo';
 *   echo $campo->crInput();
 */
class MyCampo
{
    // -------------------------------------------------------------------------
    // Constantes
    // -------------------------------------------------------------------------

    /**
     * Mapeamento de tipos SQL para categorias internas de campo.
     * Usado em doBanco() para configurar o campo automaticamente.
     */
    private const TIPOS_SQL = [
        'char'       => 'Caracter curto',
        'varchar'    => 'Caracter longo',
        'mediumtext' => 'Texto',
        'text'       => 'Texto',
        'int'        => 'Inteiro',
        'decimal'    => 'Decimal',
        'float'      => 'Moeda',
        'date'       => 'Data',
        'timestamp'  => 'Data e Hora',
        'datetime'   => 'Data e Hora',
    ];

    // -------------------------------------------------------------------------
    // Propriedades públicas — configuração do campo
    // -------------------------------------------------------------------------

    /** Tipo do objeto HTML: 'input', 'oculto', 'texto', 'select', etc. */
    public string $objeto = 'input';

    /** Tipo HTML/customizado do campo: 'text', 'date', 'moeda', 'cpf', etc. */
    public string $tipo = 'text';

    /** Atributo name do campo */
    public string $nome = '';

    /** Atributo id do campo */
    public string $id = '';

    /** Rótulo (label) exibido acima ou ao lado do campo */
    public string $label = '';

    /** Placeholder exibido dentro do campo vazio */
    public string $place = '';

    /** Tooltip exibido ao passar o mouse sobre o campo */
    public string $hint = '';

    /** Valor atual do campo */
    public string $valor = '';

    /** Função JavaScript executada no evento onchange */
    public string $funcChan = '';

    /** Função JavaScript executada no evento onblur */
    public string $funcBlur = '';

    /** Classes CSS extras aplicadas ao campo */
    public string $classep = '';

    /** Tipos de arquivo aceitos pelo campo file/imagem (ex: '.jpg,.png') */
    public string $tipoArq = '';

    /** URL usada pelo campo selbusca ou dependente para busca via AJAX */
    public string $urlbusca = '';

    /** Disposição do campo no formulário: 'linha', '2col', '3col', '4col' ou 'col-N' */
    public string $dispForm = 'linha';

    /** Pasta onde imagens do campo serão armazenadas */
    public string $pasta = '';

    /** Nome do arquivo de imagem pré-carregada */
    public string $imgName = '';

    /** Nome do campo pai, para campos do tipo dependente */
    public string $pai = '';

    /** Ícone (classe CSS) usado em botões */
    public string $i_cone = '';

    /** Texto informativo exibido acima do label */
    public string $infotop = '';

    /** Texto de alerta exibido ao lado do campo (com ícone) */
    public string $inforig = '';

    /** Texto informativo exibido abaixo do campo sem formatação especial */
    public string $infotexto = '';

    /** URL para cadastro rápido em modal vinculado ao campo */
    public string $cadModal = '';

    /** Data mínima aceita em campos do tipo date */
    public string $datamin = '';

    /** Número visível de caracteres do campo (atributo size do input) */
    public int $size = 0;

    /** Largura visual do campo em caracteres (ch) */
    public int $largura = 0;

    /** Altura do campo crShow em rem */
    public int $alturashow = 0;

    /** Máximo de caracteres permitidos (maxlength) */
    public int $maxLength = 0;

    /** Mínimo de caracteres exigidos */
    public int $minLength = 0;

    /** Valor mínimo para campos numéricos */
    public int $minimo = 0;

    /** Valor máximo para campos numéricos */
    public int $maximo = 100000;

    /** Incremento para campos numéricos (step) */
    public int $step = 1;

    /** Número de colunas do textarea */
    public int $colunas = 80;

    /** Número de linhas do textarea */
    public int $linhas = 3;

    /** Índice do campo quando em listas dinâmicas (data-index) */
    public int $ordem = -1;

    /** Se != 0, indica que alterações do campo devem ser validadas (data-valid) */
    public int $valid = 0;

    /** Array de opções para campos select, radio, checkbox, etc. */
    public array $opcoes = [];

    /** Atributos data-* extras para botões */
    public array $attrdata = [];

    /** Define se o campo é obrigatório */
    public bool $obrigatorio = false;

    /** Define se o campo é somente leitura */
    public bool $leitura = false;

    /** Item atualmente selecionado em campos com opções */
    public mixed $selecionado = null;

    /** Valor padrão do campo select (data-default) */
    public mixed $default = null;

    /** Nome da tabela no banco de dados */
    public string $tabela = '';

    /** Nome do campo na tabela */
    public string $campo = '';

    // -------------------------------------------------------------------------
    // Propriedade interna — array de atributos HTML do campo
    // -------------------------------------------------------------------------

    /** Array de atributos passados para as funções form_*() do CI4 */
    protected array $field = [];

    // =========================================================================
    // Construtor
    // =========================================================================

    /**
     * @param string $tabela    Nome da tabela no banco (opcional)
     * @param string $campo     Nome do campo na tabela (opcional)
     * @param bool   $showchave Se true, exibe campos do tipo chave primária (PRI)
     */
    public function __construct(string $tabela = '', string $campo = '', bool $showchave = false)
    {
        helper(['form', 'url']);

        $this->tabela = $tabela;
        $this->campo  = $campo;
        $this->nome   = $campo;
        $this->id     = $campo;

        // Se tabela e campo foram informados, busca metadados no banco
        if ($tabela !== '' && $campo !== '') {
            $this->doBanco($tabela, $campo, $showchave);
        }
    }

    // =========================================================================
    // Setters — interface fluente (method chaining)
    // =========================================================================

    /** Define o label (rótulo) do campo */
    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /** Define o hint (tooltip) do campo */
    public function setHint(string $hint): static
    {
        $this->hint = $hint;
        return $this;
    }

    /** Define o valor inicial do campo */
    public function setValor(mixed $valor): static
    {
        $this->valor = (string) $valor;
        return $this;
    }

    /** Define se o campo é somente leitura */
    public function setLeitura(bool $leitura = true): static
    {
        $this->leitura = $leitura;
        return $this;
    }

    /** Define as opções de seleção (select, radio, checkbox) */
    public function setOpcoes(array $opcoes): static
    {
        $this->opcoes = $opcoes;
        return $this;
    }

    /** Define o índice do campo em listas dinâmicas */
    public function setOrdem(int $ordem): static
    {
        $this->ordem = $ordem;
        return $this;
    }

    /** Define se o campo é obrigatório */
    public function setObrigatorio(bool $obrigatorio = true): static
    {
        $this->obrigatorio = $obrigatorio;
        return $this;
    }

    /** Define a disposição do campo no formulário */
    public function setDispForm(string $dispForm): static
    {
        $this->dispForm = $dispForm;
        return $this;
    }

    /** Define o item selecionado em campos com opções */
    public function setSelecionado(mixed $selecionado): static
    {
        $this->selecionado = $selecionado;
        return $this;
    }

    /** Define o tipo do campo (ex: 'text', 'moeda', 'cpf') */
    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;
        return $this;
    }

    /** Define a URL de busca dinâmica */
    public function setUrlBusca(string $url): static
    {
        $this->urlbusca = $url;
        return $this;
    }

    /** Define o campo pai para campos dependentes */
    public function setPai(string $pai): static
    {
        $this->pai = $pai;
        return $this;
    }

    /** Define a largura do campo em caracteres */
    public function setLargura(int $largura): static
    {
        $this->largura = $largura;
        return $this;
    }

    /** Define o mínimo de caracteres do campo */
    public function setMinLength(int $min): static
    {
        $this->minLength = $min;
        return $this;
    }

    /** Define o máximo de caracteres do campo */
    public function setMaxLength(int $max): static
    {
        $this->maxLength = $max;
        return $this;
    }

    /** Define o número de colunas para textarea */
    public function setColunas(int $cols): static
    {
        $this->colunas = $cols;
        return $this;
    }

    /** Define o número de linhas para textarea */
    public function setLinhas(int $linhas): static
    {
        $this->linhas = $linhas;
        return $this;
    }

    /** Define a URL do modal de cadastro rápido vinculado ao campo */
    public function setCadModal(string $url): static
    {
        $this->cadModal = $url;
        return $this;
    }

    /** Define a função JavaScript para o evento onchange */
    public function setFunChan(string $funcao): static
    {
        $this->funcChan = $funcao;
        return $this;
    }

    /** Define a função JavaScript para o evento onblur */
    public function setFunBlur(string $funcao): static
    {
        $this->funcBlur = $funcao;
        return $this;
    }

    /** Define o ícone do campo/botão (classe CSS, ex: 'fas fa-user') */
    public function setIcone(string $icone): static
    {
        $this->i_cone = $icone;
        return $this;
    }

    /** Define a pasta de armazenamento de imagens */
    public function setPasta(string $pasta): static
    {
        $this->pasta = $pasta;
        return $this;
    }

    /** Define os tipos de arquivo aceitos pelo campo file */
    public function setTipoArq(string $tipoArq): static
    {
        $this->tipoArq = $tipoArq;
        return $this;
    }

    /** Define atributos data-* extras para botões */
    public function setAttrData(array $attrdata): static
    {
        $this->attrdata = $attrdata;
        return $this;
    }

    // =========================================================================
    // Carregamento automático via banco de dados
    // =========================================================================

    /**
     * Busca metadados do campo no dicionário de dados e configura
     * automaticamente as propriedades de acordo com o tipo SQL encontrado.
     *
     * Deve ser chamado no construtor (ou manualmente logo após o new MyCampo),
     * antes de definir outras propriedades.
     */
    public function doBanco(string $tabela = '', string $campo = '', bool $showchave = false): void
    {
        if ($tabela === '' || $campo === '') {
            return;
        }

        // Usa o container do CI4 para instanciar o model (respeita cache e DI)
        $dicionario  = model(ConfigDicDadosModel::class);
        $dadosCampo  = $dicionario->getDetalhesCampo($tabela, $campo);

        if (empty($dadosCampo)) {
            return;
        }

        $dc = $dadosCampo[0];

        $this->id   = $dc['COLUMN_NAME'];
        $this->nome = $dc['COLUMN_NAME'];

        // Chaves primárias são renderizadas como campo oculto por padrão
        if ($dc['COLUMN_KEY'] === 'PRI' && ! $showchave) {
            $this->objeto = 'oculto';
            return;
        }

        $this->label = $dc['COLUMN_COMMENT'];
        $this->hint  = $dc['COLUMN_COMMENT'];
        $this->place = $this->gerarPlaceholder($dc['COLUMN_NAME'], $dc['COLUMN_COMMENT']);

        // Configura as propriedades de acordo com a categoria do tipo SQL
        $categoria = self::TIPOS_SQL[$dc['DATA_TYPE']] ?? null;

        if ($categoria !== null) {
            $this->configurarPorCategoria($categoria, $dc);
        }
    }

    /**
     * Gera o placeholder adequado com base no nome do campo.
     * Campos com sufixo _id recebem "Selecione", os demais "Informe".
     */
    private function gerarPlaceholder(string $nomeCampo, string $comentario): string
    {
        return str_contains(strtolower($nomeCampo), '_id')
            ? "Selecione $comentario"
            : "Informe $comentario";
    }

    /**
     * Configura as propriedades do campo de acordo com a categoria do tipo SQL.
     * Substitui o switch original por match() + métodos privados nomeados.
     *
     * @param string $categoria Categoria mapeada em self::TIPOS_SQL
     * @param array  $dc        Linha retornada pelo dicionário de dados
     */
    private function configurarPorCategoria(string $categoria, array $dc): void
    {
        match ($categoria) {
            'Data'          => $this->configurarData(),
            'Data e Hora'   => $this->configurarDatetime(),
            'Caracter curto' => $this->configurarCaracterCurto($dc),
            'Caracter longo' => $this->configurarCaracterLongo($dc),
            'Texto'         => $this->configurarTexto($dc),
            'Inteiro'       => $this->configurarInteiro(),
            'Decimal'       => $this->configurarDecimal($dc),
            'Moeda'         => $this->configurarMoeda(),
            default         => null,
        };
    }

    /** Configura campo do tipo date */
    private function configurarData(): void
    {
        $this->objeto  = 'input';
        $this->tipo    = 'date';
        $this->size    = 10;
        $this->largura = 25;
    }

    /** Configura campo do tipo datetime-local */
    private function configurarDatetime(): void
    {
        $this->objeto  = 'input';
        $this->tipo    = 'datetime-local';
        $this->size    = 18;
        $this->largura = 35;
    }

    /**
     * Configura campo de texto curto (char).
     * Detecta automaticamente CEP, telefone e celular pelo nome do campo.
     */
    private function configurarCaracterCurto(array $dc): void
    {
        $this->objeto = 'input';
        $this->tipo   = $this->detectarTipoTexto($dc['COLUMN_NAME']);
        $tamanho      = (int) $dc['COLUMN_SIZE'];

        if ($tamanho > 50) {
            $this->size    = 50;
            $this->maximo  = $tamanho;
            $this->largura = 55;
        } else {
            $this->size    = $tamanho;
            $this->maximo  = $tamanho;
            $this->largura = $tamanho + 8;
        }
    }

    /**
     * Configura campo de texto longo (varchar).
     * Cria textarea quando o tamanho for maior que 100 caracteres.
     */
    private function configurarCaracterLongo(array $dc): void
    {
        $tamanho = (int) $dc['COLUMN_SIZE'];

        if ($tamanho <= 100) {
            $this->objeto = 'input';
            $this->tipo   = 'text';

            if ($tamanho > 50) {
                $this->size    = 50;
                $this->maximo  = $tamanho;
                $this->largura = 58;
            } else {
                $this->size    = $tamanho;
                $this->maximo  = $tamanho;
                $this->largura = $tamanho + 8;
            }
        } else {
            $this->objeto  = 'texto';
            $this->size    = $tamanho;
            $this->maximo  = $tamanho;
            $this->linhas  = 3;
            $this->colunas = 80;
        }
    }

    /** Configura campo de texto longo (mediumtext/text) com editor */
    private function configurarTexto(array $dc): void
    {
        $this->objeto    = 'texto';
        $this->size      = (int) $dc['COLUMN_SIZE'];
        $this->minLength = 5;
        $this->linhas    = 3;
        $this->colunas   = 80;
        $this->classep   = 'editor';
    }

    /** Configura campo numérico inteiro */
    private function configurarInteiro(): void
    {
        $this->objeto  = 'input';
        $this->tipo    = 'number';
        $this->minimo  = 1;
        $this->step    = 1;
        $this->maximo  = 100000;
        $this->size    = 10;
        $this->largura = 15;
    }

    /** Configura campo numérico decimal */
    private function configurarDecimal(array $dc): void
    {
        $this->objeto  = 'input';
        $this->tipo    = 'quantia';
        $this->size    = (int) ($dc['NUMERIC_SCALE'] ?? 2);
        $this->largura = 25;
    }

    /** Configura campo de moeda (float) */
    private function configurarMoeda(): void
    {
        $this->objeto  = 'input';
        $this->tipo    = 'moeda';
        $this->size    = 12;
        $this->largura = 25;
    }

    /**
     * Detecta o tipo de campo com base no nome da coluna.
     * Retorna 'cep', 'fone', 'celular' ou 'text'.
     */
    private function detectarTipoTexto(string $nomeCampo): string
    {
        $nome = strtolower($nomeCampo);

        return match (true) {
            str_contains($nome, 'cep')    => 'cep',
            str_contains($nome, 'fone')   => 'fone',
            str_contains($nome, 'celular') => 'celular',
            default                       => 'text',
        };
    }

    // =========================================================================
    // Ajuste de ID/Nome para campos em listas dinâmicas
    // =========================================================================

    /**
     * Quando o campo faz parte de uma lista dinâmica (ordem >= 0),
     * acrescenta o índice ao nome e id antes de renderizar.
     * Ex: "produto" vira "produto[2]"
     */
    public function acertaId(): void
    {
        if ($this->ordem === -1) {
            return;
        }

        if (! str_contains($this->nome, '[')) {
            $this->nome .= "[{$this->ordem}]";
        }

        if (! str_contains($this->id, '[')) {
            $this->id .= "[{$this->ordem}]";
        }
    }

    // =========================================================================
    // Aplicação de propriedades comuns no array $field
    // =========================================================================

    /**
     * Aplica ao array $field as propriedades compartilhadas por todos os campos:
     * leitura, obrigatoriedade, hint, onchange, onblur, data-* attributes, etc.
     *
     * Deve ser chamado após a montagem inicial de $this->field em cada método cr*().
     */
    public function propriedades(): void
    {
        // Consolida o valor selecionado (pode ser array ou escalar)
        $selec = '';
        if (! isset($this->field['multiple']) && $this->selecionado !== null) {
            $selec = is_array($this->selecionado)
                ? implode(',', $this->selecionado)
                : (string) $this->selecionado;
        }

        // Atributos data-* e acessibilidade
        $this->field['data-enabled']     = $this->leitura;
        $this->field['data-alter']       = false;
        $this->field['data-valid']       = $this->valid;
        $this->field['data-live-search'] = 'true';
        $this->field['data-label']       = $this->label;
        $this->field['data-valor']       = $this->valor;
        $this->field['data-selec']       = $selec;
        $this->field['data-obrig']       = $this->obrigatorio ? 'required' : '';
        $this->field['placeholder']      = $this->place;
        $this->field['label']            = $this->label;
        $this->field['hint']             = $this->hint;
        $this->field['autocomplete']     = 'new-generation';

        // Índice de ordem em listas dinâmicas
        if ($this->ordem > -1 && $this->objeto !== 'cr2opcoes') {
            $this->field['data-index'] = $this->ordem;
        }

        // Eventos onchange e onblur (acumulativos com o que já existe no field)
        if ($this->tipo !== 'botao' && $this->tipo !== 'button') {
            if ($this->funcChan !== '') {
                $anterior                = $this->field['onchange'] ?? '';
                $this->field['onchange'] = trim($anterior . '; ' . $this->funcChan, '; ');
            }
            if ($this->funcBlur !== '') {
                $anterior               = $this->field['onblur'] ?? '';
                $this->field['onblur']  = trim($anterior . '; ' . $this->funcBlur, '; ');
            }
        }

        // Valor padrão do select
        if ($this->default !== null) {
            $this->field['data-default'] = $this->default;
        }

        // Tipo password usa HTML type="password" mas o tipo interno é 'senha'
        if ($this->tipo === 'senha') {
            $this->field['type'] = 'password';
        }

        // Tooltip via MDB
        if ($this->hint !== '') {
            $this->field['data-mdb-toggle']    = 'tooltip';
            $this->field['data-mdb-placement'] = 'top';
            $this->field['title']              = esc($this->hint);
        }

        // Campo somente leitura: desativa interação e obrigatoriedade
        if ($this->leitura) {
            $this->obrigatorio = false;
            unset($this->field['required']);
            $this->field['readonly'] = true;
            $this->field['disabled'] = 'disabled';
            $this->field['onfocus']  = 'this.blur()';
            $this->field['tabindex'] = -1;
            return;
        }

        // Campo obrigatório (exceto tipos que gerenciam a validação externamente)
        $tiposExcluidos = ['login', 'password', 'senha'];
        if ($this->obrigatorio && ! in_array($this->tipo, $tiposExcluidos, true)) {
            unset($this->field['readonly'], $this->field['disabled']);
            $this->field['required'] = true;

            if ($this->tipo === 'text' || $this->objeto === 'texto') {
                // Corrigido: usa o valor real de minLength, não isset()
                $this->field['minLength'] = $this->minLength > 0 ? $this->minLength : 5;
            }
        }
    }

    // =========================================================================
    // Renderização do label
    // =========================================================================

    /**
     * Renderiza o label flutuante acima do campo.
     * Campos do tipo file/imagem recebem estilo de botão.
     */
    public function crLabel(): string
    {
        $ident = $this->id;

        $atributosLabel = ['class' => 'form-label p-0 m-0'];
        $textoLabel     = esc($this->label);

        // Para campos de arquivo, o label vira um botão de seleção
        if (in_array($this->tipo, ['file', 'imagem'], true)) {
            $atributosLabel['class'] = 'btn btn-primary';
            $atributosLabel['style'] = "white-space: normal;width:{$this->largura};padding:0.8em;";
            $textoLabel             .= "<i class='fa fa-file-archive-o'></i> Selecionar Arquivo";
        }

        $mb = $this->objeto === 'cr2opcoes' ? 'mb-4 mt-0' : 'mb-4';

        $html  = "<div class='position-relative {$mb}' style='z-index:110'>";
        $html .= "<div class='text-nowrap text-start d-inline-block position-absolute ms-2 pe-2 py-0'>Lab";
        $html .= form_label($textoLabel, $ident, $atributosLabel);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    // =========================================================================
    // Renderização do wrapper de layout
    // =========================================================================

    /**
     * Envolve o campo renderizado com o wrapper de layout (grid Bootstrap),
     * label, mensagens de validação, botão de modal e textos informativos.
     *
     * @param string $campo    HTML do campo em si
     * @param string $groupant HTML inserido antes do campo (ex: ícone prefix)
     * @param string $grouppos HTML inserido após o campo (ex: ícone suffix, ou 'arquivo' para upload)
     */
    public function fmtDisplay(string $campo, string $groupant = '', string $grouppos = ''): string
    {
        $colunas = $this->resolverColunas();
        $mb      = str_contains($this->classep, 'semmb') ? 'm-0' : 'mb-3';
        $ht       = 'nãoauto';

        if ($this->tipo === 'check') {
            $ht = 'h-auto';
        }

        $html  = "<div id='ig_{$this->id}' class='row {$colunas} float-start align-items-center d-inline-flex {$mb} {$ht}'>";

        // Texto informativo acima do label
        if ($this->infotop !== '') {
            $html .= "<div class='text-info'><i class='fa-solid fa-bullhorn'></i> " . esc($this->infotop) . '</div>';
        }

        // Label
        if ($this->label !== '') {
            $html .= $this->crLabel();
        }

        // Wrapper do input com largura e validação
        $html .= $this->abrirWrapperInput();
        $html .= $groupant;
        $html .= $campo;

        // Suffix especial para campos de arquivo (exibe preview e nome)
        if ($grouppos === 'arquivo') {
            $html .= $this->renderizarSuffixArquivo();
        } else {
            $html .= $grouppos;
        }

        // Mensagens de validação
        if (! $this->leitura) {
            $html .= $this->renderizarMensagensValidacao();
        }

        // Caixa de info de senha
        if (in_array($this->tipo, ['password', 'senha'], true)) {
            $html .= "<div id='pass-info' class='invalid-feedback border border-1 bg-white
                        position-content p-2 text-start' style='z-index:200;top:2rem'></div>";
        }

        $html .= '</div>'; // fecha input-group

        // Texto de alerta lateral
        if ($this->inforig !== '') {
            $html .= "<div class='text-warning fst-italic w-auto ms-3'>
                        <i class='fa-solid fa-triangle-exclamation'></i> " . esc($this->inforig) . '</div>';
        }

        if ($this->infotexto !== '') {
            $html .= $this->infotexto;
        }

        // Botão de cadastro rápido em modal
        if ($this->cadModal !== '' && ! $this->leitura) {
            $html .= $this->renderizarBotaoModal();
        }

        $html .= '</div>'; // fecha row

        return $html;
    }

    /**
     * Retorna as classes de colunas Bootstrap de acordo com dispForm.
     */
    private function resolverColunas(): string
    {
        if (str_contains($this->dispForm, 'col-')) {
            return $this->dispForm; // ex: 'col-5 col-lg-5'
        }

        return match (true) {
            str_contains($this->dispForm, '4col') => 'col-3 col-lg-3',
            str_contains($this->dispForm, '3col') => 'col-4 col-lg-4',
            str_contains($this->dispForm, '2col') => 'col-6 col-lg-6',
            default                               => 'col-12 col-lg-12',
        };
    }

    /**
     * Abre a div do input-group com largura e classes de validação/leitura.
     */
    private function abrirWrapperInput(): string
    {
        $hasvalid = $this->tipo === 'cpf' ? ' has-validation' : '';
        $disabled = $this->leitura ? 'disabled' : '';

        if ($this->tipo === 'check') {
            return "<div class='mt-0{$hasvalid} {$disabled}' style='width: auto !important;'>";
        }

        if ($this->tipo === 'editor') {
            return "<div class='input-group mt-0{$hasvalid} {$disabled}'>";
        }

        if ($this->largura > 0) {
            $auto    = 'auto';
            $largura = "{$this->largura}ch";

            if (in_array($this->tipo, ['select', 'number'], true)) {
                $auto = $largura;
            }

            $mbNum = $this->tipo === 'number' ? 'mb-0' : '';
            return "<div class='input-group mt-0 {$mbNum}{$hasvalid} {$disabled}'
                        style='width: {$auto} !important; max-width: {$largura} !important;'>";
        }

        return "<div class='input-group mt-0{$hasvalid} {$disabled}' style='width: auto !important;'>";
    }

    /**
     * Renderiza o suffix de arquivos: nome do arquivo, preview e imagem.
     */
    private function renderizarSuffixArquivo(): string
    {
        $html  = "<div id='nome_arquivo_img_{$this->nome}' class='text-break border p-1 mb-2'
                    style='width: auto;'>" . esc($this->valor) . '</div>';

        if ($this->funcBlur !== '') {
            $onclick = str_replace("'", '"', $this->funcBlur);
            $html   .= "<div id='view_img_{$this->nome}' class='show img-thumbnail' onclick='{$onclick}'>";
            $html   .= "<div class='text-black'>Clique para Visualizar</div>";
        } else {
            $html .= "<div id='view_img_{$this->nome}' class='show img-thumbnail'>";
        }

        $html .= "<img id='img_{$this->nome}' src='" . esc($this->selecionado ?? '') . "'
                    for='{$this->id}' class='img-thumbnail sempadding' alt=''
                    style='width:{$this->size}px;' />";
        $html .= '</div>';
        $html .= '</label>';

        return $html;
    }

    /**
     * Renderiza as mensagens de feedback de validação (Bootstrap).
     * CPF tem feedback duplo: obrigatoriedade + formato.
     */
    private function renderizarMensagensValidacao(): string
    {
        $html  = "<div id='{$this->id}-fival' class='invalid-feedback'>";
        $html .= esc($this->label) . ' é obrigatório';
        $html .= '</div>';

        if ($this->tipo === 'cpf') {
            $html .= "<div id='{$this->id}-fival' class='invalid-feedback'>CPF Inválido, verifique!</div>";
        }

        return $html;
    }

    /**
     * Renderiza o botão de cadastro rápido que abre modal.
     */
    private function renderizarBotaoModal(): string
    {
        $fieldBtn = [
            'name'    => 'bt_ad_' . $this->nome,
            'id'      => 'bt_ad_' . $this->id,
            'style'   => 'width:2.5rem',
            'type'    => 'button',
            'class'   => 'btn btn-outline-secondary m-0',
            'content' => "<i class='fa-solid fa-wand-sparkles fa-flip-horizontal'></i>",
            'onclick' => "openModal('" . esc($this->cadModal) . "')",
        ];

        return form_button($fieldBtn);
    }

    // =========================================================================
    // Métodos de exibição (sem edição)
    // =========================================================================

    /**
     * Exibe o valor em formato de campo de leitura (div estilizada).
     * Útil para telas de visualização de registros.
     */
    public function crShow(): string
    {
        $altu = isset($this->alturashow) && $this->alturashow > 0
            ? "{$this->alturashow}rem"
            : '30rem';

        $larg = $this->largura > 0 ? "{$this->largura}ch" : '100%';

        $html  = "<div class='row {$this->dispForm} align-items-center float-start d-inline-flex'>";
        if ($this->label !== '') {
            $html .= $this->crLabel();
        }
        $html .= "<div class='input-group mb-lg-1 mb-2
                    overflow-auto form-control disabled text-break'
                    style='height: {$altu} !important'>";
        $html .= $this->valor;
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Retorna apenas o valor do campo sem qualquer formatação HTML.
     */
    public function crTextShow(): string
    {
        return $this->valor;
    }

    // =========================================================================
    // Campos de ação
    // =========================================================================

    /**
     * Cria um botão de ação.
     * O label é exibido no atributo title e o conteúdo HTML em $i_cone.
     */
    public function crBotao(): string
    {
        $this->acertaId();

        if ($this->tipo === '' || $this->tipo === 'text') {
            $this->tipo = 'button';
        }

        $this->field = [
            'name'    => $this->nome,
            'id'      => $this->id,
            'type'    => $this->tipo,
            'class'   => "btn {$this->classep}",
            'content' => $this->i_cone,
            'onclick' => $this->funcChan,
            'title'   => $this->place,
        ];

        $this->propriedades();

        // Atributos data-* extras informados via setAttrData()
        foreach ($this->attrdata as $key => $value) {
            $this->field[$key] = $value;
        }

        return form_button($this->field);
    }

    /**
     * Cria um campo hidden (oculto).
     * Usado internamente para campos de chave primária ou valores de controle.
     */
    public function crOculto(): string
    {
        $this->acertaId();

        $this->field = [
            'type'  => 'hidden',
            'name'  => $this->nome,
            'id'    => $this->nome,
            'value' => $this->valor,
        ];

        return form_input($this->field);
    }

    // =========================================================================
    // Campos de seleção binária (check / radio)
    // =========================================================================

    /**
     * Cria um checkbox no formato switch (toggle).
     * O valor selecionado é definido pela comparação entre $valor e $selecionado.
     */
    public function crCheckbox(): string
    {
        $this->acertaId();
        $this->tipo = 'check';

        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'         => $this->nome,
            'id'           => $this->id,
            'value'        => $this->valor,
            'role'         => 'switch',
            'data-selec'   => $this->selecionado,
            'data-enabled' => $this->leitura,
            'data-alter'   => false,
            'data-label'   => $this->label,
            'label'        => $this->label,
            'hint'         => $this->hint,
            'onchange'     => $this->funcChan,
            'class'        => "form-check-input {$this->classep}",
        ];

        if ($this->valor == $this->selecionado) {
            $this->field['checked'] = true;
        }

        $this->propriedades();
        $campo = form_checkbox($this->field);

        $html  = "<div class='form-check form-switch p-0 {$this->dispForm}'>";
        $html .= $this->fmtDisplay($campo);
        $html .= '</div>';

        return $html;
    }

    /**
     * Cria checkboxes em formato de botão (estilo Bootstrap btn-check).
     * Permite múltipla seleção com visual de botão.
     */
    public function crCheckbutton(): string
    {
        $this->acertaId();
        $this->tipo = 'check';

        $itens = '';
        $cont  = 0;

        foreach ($this->opcoes as $valor => $textoLabel) {
            $id                = "{$this->id}[{$cont}]";
            $this->selecionado ??= $valor;

            $this->field = [
                'name'  => $this->nome,
                'id'    => $id,
                'value' => $valor,
                'class' => 'btn-check ui-state-default position-fixed',
            ];

            $checked = ($valor == $this->selecionado);
            $this->propriedades();

            $label  = "<label class='btn {$this->classep} fs-4' for='{$id}'> {$textoLabel} </label>";
            $itens .= "<div class='d-inline-flex me-2 col-12'>";
            $itens .= form_checkbox($this->field, '', $checked) . $label;
            $itens .= '</div>';
            $cont++;
        }

        // Monta a lista de botões e envolve no wrapper de layout
        $campo  = "<div class='form-check form-switch form-check-inline form-control px-1
                    sort overflow-auto overflow-x-hidden' style='width: auto; max-height: 70vh;'>";
        $campo .= $itens;
        $campo .= '</div>';

        return $this->fmtDisplay($campo);
    }

    /**
     * Alias para crRadio().
     * Mantém compatibilidade com chamadas existentes a cr2opcoes().
     */
    public function cr2opcoes(): string
    {
        return $this->crRadio();
    }

    /**
     * Cria um grupo de inputs radio com visual padrão.
     */
    public function crRadio(): string
    {
        $this->acertaId();
        $this->tipo = 'check';

        $itens = '';
        $cont  = 0;

        foreach ($this->opcoes as $valor => $textoLabel) {
            $id                = "{$this->id}[{$cont}]";
            $this->selecionado ??= $valor;

            $this->field = [
                'name'       => $this->nome,
                'id'         => $id,
                'value'      => $valor,
                'data-selec' => $this->selecionado,
                'data-salva' => true,
                'class'      => 'form-check-input ml-2',
            ];

            if ($valor == $this->selecionado) {
                $this->field['checked'] = true;
            }

            $this->propriedades();

            $label  = "<label class='form-check-label px-1 m-auto mx-0' for='{$id}'> {$textoLabel} </label>";
            $itens .= "<div class='d-inline-flex {$this->classep}' style='width: auto'>";
            $itens .= form_radio($this->field) . $label;
            $itens .= '</div>';
            $cont++;
        }

        $campo  = "<div class='form-check form-switch form-check-inline form-control px-1 py-1 m-0'
                    style='width: auto'>";
        $campo .= $itens;
        $campo .= '</div>';

        return $this->fmtDisplay($campo);
    }

    /**
     * Cria um grupo de inputs radio com visual de botão (Bootstrap btn-check).
     */
    public function crRadiobutton(): string
    {
        $this->acertaId();
        $this->tipo = 'check';

        $itens = '';
        $cont  = 0;

        foreach ($this->opcoes as $valor => $textoLabel) {
            $id                = "{$this->id}[{$cont}]";
            $this->selecionado ??= $valor;

            $this->field = [
                'name'         => $this->nome,
                'id'           => $id,
                'value'        => $valor,
                'autocomplete' => 'off',
                'class'        => 'btn-check ui-state-default position-fixed',
            ];

            $this->propriedades();

            $checked = ($valor == $this->selecionado);
            if ($checked) {
                $this->field['checked'] = true;
            }

            $label  = "<label class='btn {$this->classep} fs-4' for='{$id}'> {$textoLabel} </label>";
            $itens .= "<div class='d-inline-flex me-2 col-12'>";
            $itens .= form_radio($this->field, '', $checked) . $label;
            $itens .= '</div>';
            $cont++;
        }

        $campo  = "<div class='form-check form-switch form-check-inline form-control px-1
                    sort overflow-auto overflow-x-hidden' style='width: auto; max-height: 70vh;'>";
        $campo .= $itens;
        $campo .= '</div>';

        return $this->fmtDisplay($campo);
    }

    // =========================================================================
    // Campo de texto principal (input)
    // =========================================================================

    /**
     * Cria o campo input principal.
     * Redireciona para crTexto() ou crOculto() quando o objeto assim determinar.
     * Configura os atributos específicos de cada tipo antes de renderizar.
     */
    public function crInput(): string
    {
        $this->acertaId();

        // Redireciona para textarea
        if ($this->objeto === 'texto') {
            return $this->crTexto();
        }

        // Redireciona para campo oculto
        if ($this->objeto === 'oculto') {
            return $this->crOculto();
        }

        $groupant = '';
        $grouppos = '';
        $html     = '';

        $leng = $this->maxLength > 0 ? $this->maxLength : $this->size;
        $pe   = $this->leitura ? 'pe-none' : 'pe-auto';

        // Monta os atributos base do campo
        $this->field = [
            'type'         => $this->tipo,
            'name'         => $this->nome,
            'id'           => $this->id,
            'value'        => $this->valor,
            'size'         => $this->size,
            'maxlength'    => $leng,
            'class'        => "form-control {$this->classep}",
            'data-inicial' => $this->valor,
            'data-nome'    => $this->campo,
        ];

        // Configura atributos específicos por tipo e retorna groupant/grouppos
        [$groupant, $grouppos, $html] = $this->aplicarConfiguracoesDeTipo($leng, $pe, $html);

        // Contador de caracteres
        $grouppos .= "<div id='dc-{$this->id}' class='div-caract badge bg-info-subtle'></div>";

        $this->propriedades();
        $campo = form_input($this->field);

        return $html . $this->fmtDisplay($campo, $groupant, $grouppos);
    }

    /**
     * Aplica as configurações específicas de cada tipo de campo (ex: moeda, cpf, senha).
     * Retorna [groupant, grouppos, htmlPrefixo].
     *
     * Cada tipo pode:
     *   - Alterar atributos em $this->field
     *   - Adicionar elementos antes (groupant) ou após (grouppos) o input
     *   - Acrescentar HTML fora do wrapper (htmlPrefixo), como scripts ou campos ocultos
     */
    private function aplicarConfiguracoesDeTipo(int $leng, string $pe, string $html): array
    {
        $groupant = '';
        $grouppos = '';

        switch ($this->tipo) {

            case 'color':
                $this->field['type']             = 'color';
                $this->field['aria-describedby'] = 'ig_' . $this->nome;
                break;

            case 'icone':
                $this->field['type']             = 'text';
                $this->field['class']           .= ' icone';
                $this->field['aria-describedby'] = 'ig_' . $this->nome;
                $groupant = "<span class='input-group-text input-group-addon'>
                                <i class='" . esc($this->valor) . "'></i></span>";
                break;

            case 'sonumero':
                $this->field['type']             = 'text';
                $this->field['onkeyup']          = "mascara(this, 'mnum')";
                $this->field['onchange']         = "mascara(this, 'mnum')";
                $this->field['pattern']          = "^[0-9-]{1,{$leng}}$";
                $this->field['style']            = 'text-align: right';
                $this->field['aria-describedby'] = 'ig_' . $this->nome;
                break;

            case 'quantia':
                $this->field['type']             = 'text';
                $this->field['onkeyup']          = "mascara(this, 'mquantia')";
                $this->field['onblur']           = $this->funcBlur;
                $this->field['value']            = floatToQuantia($this->valor, $this->size);
                $this->field['pattern']          = "^([\d]*\,?[\d]{0,{$leng}})$";
                $this->field['style']            = 'text-align: right';
                $this->field['aria-describedby'] = 'ig_' . $this->nome;
                break;

            case 'inteiro':
                $this->field['dir']              = 'rtl';
                $this->field['min']              = $this->minimo;
                $this->field['max']              = $this->maximo;
                $this->field['step']             = $this->step;
                $this->field['oninput']          = "this.value = acertaMaximo(this, {$leng})";
                $this->field['onkeyup']          = "mascara(this, 'mnum'); this.value = acertaMaximo(this, {$leng});";
                $this->field['onchange']         = "mascara(this, 'mnum')";
                $this->field['pattern']          = "^[1-9][0-9]{0,{$leng}}$";
                $this->field['style']            = 'text-align: right;';
                $this->field['aria-describedby'] = 'ig_' . $this->nome;
                $this->field['class']           .= ' form-number';
                $this->largura                  += 10;
                [$groupant, $grouppos]           = $this->buildNumericControls($pe);
                break;

            case 'number':
                $this->field['dir']     = 'rtl';
                $this->field['min']     = $this->minimo;
                $this->field['max']     = $this->maximo;
                $this->field['step']    = $this->step;
                $this->field['onfocus'] = 'entrar_moeda(this)';
                $this->field['onkeyup'] = "mascara(this, 'mnum'); this.value = acertaMaximo(this, {$leng});";
                $this->field['pattern'] = "^[1-9][0-9]{0,{$leng}}$";
                $this->field['style']   = 'text-align: right;';
                $this->field['class']  .= ' form-number';
                $this->largura         += 10;
                [$groupant, $grouppos]  = $this->buildNumericControls($pe);
                break;

            case 'moeda':
                $this->field['type']        = 'text';
                $this->field['onkeyup']     = "mascara(this, 'mvalor')";
                $this->field['pattern']     = "^([\$]?)([0-9]*\,?[0-9]{0,2})$";
                $this->field['onchange']    = $this->funcChan;
                $this->field['data-origin'] = floatToMoeda($this->valor);
                $this->field['value']       = floatToMoeda($this->valor);
                $this->field['data-person'] = '0';
                $this->field['onblur']      = 'sair_moeda(this);' . $this->funcBlur;
                $this->field['onfocus']     = 'entrar_moeda(this)';
                $this->field['class']      .= ' moeda has-validation';
                $this->field['style']       = 'text-align: right';
                break;

            case 'date':
            case 'datetime-local':
                if ($this->datamin !== '') {
                    $this->field['min'] = $this->datamin;
                }
                break;

            case 'senha':
            case 'password':
                // Campo oculto para enganar o autocomplete do navegador
                $campoFake = [
                    'type'     => 'password',
                    'name'     => 'enganagoogle',
                    'value'    => '',
                    'tabindex' => -1,
                    'style'    => 'left:0;opacity:0;position:absolute;',
                ];
                $html                           .= form_input($campoFake);
                $this->field['class']            = "form-control {$this->classep} password";
                $this->field['onchange']         = $this->funcChan;
                $this->field['onblur']           = 'validaSenha(this);oculta_passinfo();' . $this->funcBlur;
                $this->field['aria-describedby'] = 'ad_' . $this->nome;
                $groupant .= "<span class='input-group-text input-group-addon' id='ad_{$this->nome}'>
                                <i class='fa-solid fa-key'></i></span>";
                $grouppos .= "<span name='show_password' class='input-group-text input-group-append
                                show_password'
                                id='ada_{$this->nome}' data-field='{$this->nome}'>
                                <i class='fa-solid fa-eye-slash'></i>
                            </span>";
                break;

            case 'email':
                $this->field['type']                = 'email';
                $this->field['pattern']             = '^[\w\.=-]+@[\w\.-]+\.[\w]{2,3}$';
                $this->field['style']               = 'text-align: left';
                $this->field['aria-describedby']    = 'ad_' . $this->nome;
                $this->field['title']               = 'Informe um E-mail válido!';
                $grouppos .= "<span class='input-group-text input-group-append' id='ad_{$this->nome}'>
                                <i class='far fa-envelope-open'></i></span>";
                break;

            case 'site':
            case 'url':
                $this->field['type']             = 'url';
                $this->field['pattern']          = '^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$';
                $this->field['style']            = 'text-align: left';
                $this->field['aria-describedby'] = 'ad_' . $this->nome;
                $this->field['title']            = 'Informe uma URL válida!';
                $grouppos .= "<span class='input-group-text input-group-append' id='ad_{$this->nome}'>
                                <i class='far fa-link'></i></span>";
                break;

            case 'telefone':
            case 'fone':
                $this->field['type']             = 'tel';
                $this->field['pattern']          = '^\(\d{2}\) \d{4}\-\d{4}$';
                $this->field['onkeyup']          = "mascara(this, 'mtel')";
                $this->field['style']            = 'text-align: left';
                $this->field['aria-describedby'] = 'ad_' . $this->nome;
                $this->field['title']            = 'Informe um Telefone válido! (99) 9999-9999';
                // Corrigido: tag estava mal fechada na versão original
                $grouppos .= "<span class='input-group-text input-group-append' id='ad_{$this->nome}'>
                                <i class='fas fa-phone'></i></span>";
                break;

            case 'celular':
            case 'celul':
            case 'whatsapp':
            case 'whats':
                $this->field['type']             = 'tel';
                $this->field['pattern']          = '^\(\d{2}\) \d{4,5}\-\d{4}$';
                $this->field['onkeyup']          = "mascara(this, 'mcel2')";
                $this->field['style']            = 'text-align: left';
                $this->field['aria-describedby'] = 'ad_' . $this->nome;
                $this->field['title']            = 'Informe um Celular válido! (99) 99999-9999';
                $grouppos .= "<span class='input-group-text input-group-append' id='ad_{$this->nome}'>
                                <i class='fa fa-mobile-alt'></i></span>";
                break;

            case 'cnpj':
                $this->field['type']    = 'text';
                $this->field['pattern'] = '^\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}$';
                $this->field['onkeyup'] = "mascara(this, 'mcnpj')";
                $this->field['style']   = 'text-align: right';
                $this->field['title']   = 'Digite o CNPJ no formato 99.999.999/9999-99';
                break;

            case 'cpf':
                $this->field['type']    = 'text';
                $this->field['pattern'] = '^\d{3}\.\d{3}\.\d{3}\-\d{2}$';
                $this->field['onkeyup'] = "mascara(this, 'mcpf')";
                $this->field['onblur']  = ($this->field['onblur'] ?? '') . ';ValidaCPF(this)';
                $this->field['style']   = 'text-align: right';
                $this->field['title']   = 'Digite o CPF no formato 999.999.999-99';
                break;

            case 'cep':
                $this->field['type']    = 'text';
                $this->field['pattern'] = '^\d{5}\-\d{3}$';
                $this->field['onkeyup'] = "mascara(this, 'mcep')";
                $this->field['style']   = 'text-align: right';
                $this->field['title']   = 'Digite o CEP no formato 99999-999';
                break;

            case 'placaveiculo':
                $this->field['type']    = 'text';
                $this->field['pattern'] = '^[A-Z]{3}\-\d[A-Z0-9]\d{2}$';
                $this->field['onkeyup'] = "mascara(this, 'mplaca')";
                $this->field['class']   = "form-control {$this->classep} text-uppercase";
                $this->field['style']   = 'text-align: left';
                $this->field['title']   = 'Informe uma Placa Válida! AAA-0000 ou AAA-0A00';
                break;

            case 'ip':
                $this->field['type']  = 'text';
                $this->field['class'] = "form-control {$this->classep}";
                $this->field['style'] = 'text-align: left';
                $this->field['title'] = 'Informe um Endereço IP válido! 000.000.000.000';
                break;

            case 'file':
                $this->field['type']          = 'file';
                $this->field['data_folder']   = $this->pasta;
                $this->field['data_img_name'] = $this->imgName;
                $this->field['class']         = '';
                $icoArq                       = $this->valor !== ''
                    ? substr($this->valor, strrpos($this->valor, '.') + 1) . '.png'
                    : '';
                $grouppos .= "<div id='view_img_{$this->nome}' class='show clearfix'
                                style='width:200px;height:200px;'>";
                $grouppos .= "<img id='img_{$this->nome}' src='" .
                    base_url('uploads/tipo_down/') . $icoArq .
                    "' for='{$this->id}' class='img-thumbnail col-lg-12 col-xs-12'
                    style='width:200px;height:200px;' alt='' /></div>";
                break;

            // Exibe o texto do select correspondente ao valor de outro campo
            case 'textselect':
                $this->field['type'] = 'text';
                $busca               = "buscaTextselect(this,\"{$this->nome}\")";
                $html               .= $this->buildScriptTextselect($this->place, $busca, 'change');
                break;

            // Guarda o texto do select em campo oculto
            case 'textselectoculto':
                $this->field['type'] = 'hidden';
                $busca               = "buscaTextselect(this,\"{$this->nome}\")";
                $html               .= $this->buildScriptTextselect($this->place, $busca, 'blur');
                break;

            // Campo calculado com resultado de expressão
            case 'calculo':
                $this->field['placeholder'] = '';
                $busca                      = "calcula(\"{$this->id}\",\"{$this->place}\",\"{$this->pai}\")";
                $html .= "<script>";
                $html .= "jQuery('#{$this->valor}').attr('onchange','{$busca}');";
                $html .= "jQuery('#{$this->valor}').trigger('change');";
                $html .= '</script>';
                break;
        }

        return [$groupant, $grouppos, $html];
    }

    /**
     * Cria os botões de incremento/decremento para campos numéricos (+ e -).
     * Retorna [groupant, grouppos].
     */
    private function buildNumericControls(string $pe): array
    {
        if ($this->leitura) {
            $this->field['style'] = ($this->field['style'] ?? '') . ' padding-right: 12px !important';
            return ['', ''];
        }

        $groupant = "<div class='input-group-text input-group-addon down-num {$pe}'
                        data-refer='{$this->id}' id='dw_{$this->nome}'>
                        <i class='fas fa-minus'></i></div>";

        $grouppos = "<div class='input-group-text input-group-append up-num {$pe}'
                        data-refer='{$this->id}' id='up_{$this->nome}'>
                        <i class='fas fa-plus'></i></div>";

        return [$groupant, $grouppos];
    }

    /**
     * Gera o bloco <script> para campos textselect e textselectoculto.
     *
     * @param string $refCampo  ID do campo select de referência
     * @param string $busca     Função JS a ser executada
     * @param string $evento    Evento jQuery: 'change' ou 'blur'
     */
    private function buildScriptTextselect(string $refCampo, string $busca, string $evento): string
    {
        $html  = '<script>';
        $html .= "chang_ant = jQuery('#{$refCampo}').attr('onchange');";
        $html .= "jQuery('#{$refCampo}').attr('onchange', chang_ant + '{$busca}');";
        $html .= "jQuery('#{$refCampo}').trigger('{$evento}');";
        $html .= '</script>';

        return $html;
    }

    // =========================================================================
    // Campos de texto longo
    // =========================================================================

    /**
     * Cria um campo de período (date range com duas datas).
     */
    public function crDaterange(): string
    {
        $this->acertaId();

        $this->field = [
            'type'      => 'text',
            'name'      => $this->nome,
            'id'        => $this->id,
            'value'     => $this->valor,
            'size'      => $this->size,
            'maxlength' => $this->maxLength > 0 ? $this->maxLength : $this->size,
            'class'     => "daterange form-control {$this->classep}",
        ];

        $this->propriedades();

        return $this->fmtDisplay(form_input($this->field));
    }

    /**
     * Cria um textarea com editor de texto rico (TinyMCE, CKEditor, etc.).
     * A classe 'editor' ativa o editor via JavaScript.
     */
    public function crEditor(): string
    {
        $this->acertaId();
        $this->tipo    = 'editor';
        $this->colunas = 100;

        $this->field = [
            'type'  => 'textarea',
            'name'  => $this->nome,
            'id'    => $this->id,
            'value' => $this->valor,
            'class' => "{$this->classep} form-control",
        ];

        $this->propriedades();

        return $this->fmtDisplay(form_textarea($this->field));
    }

    /**
     * Cria um textarea simples (sem editor rico).
     * Inclui contador de caracteres via JS.
     */
    public function crTexto(): string
    {
        $this->acertaId();

        $this->field = [
            'type'      => 'textarea',
            'name'      => $this->nome,
            'id'        => $this->id,
            'value'     => $this->valor,
            'cols'      => $this->colunas,
            'rows'      => $this->linhas,
            'maxlength' => $this->maxLength > 0 ? $this->maxLength : $this->size,
            'class'     => 'form-control',
        ];

        $this->propriedades();

        $grouppos = "<div id='dc-{$this->id}' class='div-caract badge bg-info-subtle'></div>";

        return $this->fmtDisplay(form_textarea($this->field), '', $grouppos);
    }

    // =========================================================================
    // Campos de seleção (select e variantes)
    // =========================================================================

    /**
     * Cria um select com duas listas (dual listbox).
     * Itens são movidos entre as listas por clique ou arrastar.
     */
    public function crDual(): string
    {
        $this->acertaId();
        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'         => $this->nome,
            'id'           => $this->id,
            'data-enabled' => $this->leitura,
            'data-alter'   => false,
            'data-label'   => $this->label,
            'data-valor'   => $this->selecionado,
            'data-size'    => $this->size,
            'placeholder'  => $this->place,
            'hint'         => $this->hint,
            'multiple'     => 'multiple',
            'onchange'     => $this->funcChan,
            'class'        => 'form-control form-dual',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        if ($this->place !== '') {
            $this->opcoes = ['-1 disabled' => 'Escolha ' . $this->place] + $this->opcoes;
        }

        $this->propriedades();

        return $this->fmtDisplay(
            form_dropdown($this->field, $this->opcoes, $this->selecionado)
        );
    }

    /**
     * Cria um select com seleção múltipla (Bootstrap Select com checkboxes).
     */
    public function crMultiple(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->selecionado ??= $this->valor;

        // Normaliza selecionado como array
        if (! is_array($this->selecionado)) {
            $this->selecionado = ($this->selecionado !== null && $this->selecionado !== '')
                ? array_filter(explode(',', (string) $this->selecionado))
                : [];
        }

        $this->nome .= '[]';
        $this->id   .= '[]';

        $this->field = [
            'name'             => $this->nome,
            'id'               => $this->id,
            'data-container'   => 'body',
            'multiple'         => 'multiple',
            'data-live-search' => 'true',
            'class'            => 'selectpicker form-control form-select show-tick',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();

        $extras = [
            'data-actions-box'          => 'true',
            'data-size'                 => '2',
            'data-selected-text-format' => 'count > 1',
            'style'                     => 'height:130px;overflow-y:auto;max-height:130px;',
        ];

        $campo = form_multiselect($this->field, $this->opcoes, $this->selecionado, $extras);

        return $this->fmtDisplay($campo);
    }

    /**
     * Cria um select onde cada opção tem um ícone ao lado do texto.
     * As opções devem ser passadas no formato:
     *   $opcoes['texto'] = [valor => label]
     *   $opcoes['icone'] = [valor => html_do_icone]
     */
    public function crSelectIcone(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'  => $this->nome,
            'id'    => $this->id,
            'class' => 'form-control form-select selectpicker',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();
        $this->field['placeholder'] = str_replace('Informe', 'Selecione', $this->field['placeholder']);

        $campo = form_dropdown($this->field, $this->opcoes['texto'] ?? [], $this->selecionado);

        // Injeta os ícones via jQuery nas opções do select
        if (! empty($this->opcoes['icone'])) {
            $campo .= '<script>';
            foreach ($this->opcoes['icone'] as $key => $icone) {
                $campo .= "jQuery('#{$this->id} option[value=\"{$key}\"]').attr('data-content','{$icone}');";
            }
            $campo .= '</script>';
        }

        return $this->fmtDisplay($campo);
    }

    /**
     * Cria um select simples com Bootstrap Select (selectpicker).
     */
    public function crSelect(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'           => $this->nome,
            'id'             => $this->id,
            'data-container' => 'body',
            'class'          => 'form-control form-select selectpicker',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();
        $this->field['placeholder'] = str_replace('Informe', 'Selecione', $this->field['placeholder']);

        return $this->fmtDisplay(
            form_dropdown($this->field, $this->opcoes, $this->selecionado)
        );
    }

    /**
     * Cria um select de cores do Bootstrap (bg-primary, bg-danger, etc.).
     * Cada opção exibe uma amostra colorida via Bootstrap Select.
     */
    public function crCorbst(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->selecionado ??= $this->valor;

        $this->field = [
            'type'  => 'color',
            'list'  => 'bstColors',
            'name'  => $this->nome,
            'id'    => $this->id,
            'class' => 'form-control form-select form-color selectpicker',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();
        $this->field['placeholder'] = str_replace('Informe', 'Selecione', $this->field['placeholder']);

        // Cores disponíveis do Bootstrap
        $coresBst = [
            'bg-primary'   => '',
            'bg-secondary' => '',
            'bg-success'   => '',
            'bg-danger'    => '',
            'bg-warning'   => '',
            'bg-info'      => '',
            'bg-dark'      => '',
            'bg-white'     => '',
        ];

        $campo  = form_dropdown($this->field, $coresBst, $this->selecionado);
        $campo .= '<script>';

        foreach (array_keys($coresBst) as $cor) {
            $etiqueta = fmtEtiquetaCorBst($cor);
            $campo   .= "jQuery(\"#{$this->id} option[value='{$cor}']\").attr('data-content',\"{$etiqueta}\");";
        }

        $campo .= '</script>';

        return $this->fmtDisplay($campo);
    }

    /**
     * Cria um select de cores personalizadas do sistema.
     * As opções devem conter o código hex no formato "Nome da Cor #RRGGBB".
     */
    public function crSelectCor(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'           => $this->nome,
            'id'             => $this->id,
            'data-container' => 'body',
            'class'          => 'form-control form-select form-color selectpicker',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();
        $this->field['placeholder'] = str_replace('Informe', 'Selecione', $this->field['placeholder']);

        $campo  = form_dropdown($this->field, $this->opcoes, $this->selecionado);
        $campo .= '<script>';

        foreach ($this->opcoes as $key => $valor) {
            // Extrai o código hex (últimos 7 chars) e o nome da cor
            $rgb     = substr(trim($valor), -7);
            $nome    = substr($valor, 0, strpos($valor, '#') - 3);
            $cor     = fmtEtiquetaCor($rgb, $nome);
            $campo  .= "jQuery(\"#{$this->id} option[value='{$key}']\").attr('data-content',\"{$cor}\");";
        }

        $campo .= '</script>';

        return $this->fmtDisplay($campo);
    }

    /**
     * Cria um select com busca de opções via AJAX (selbusca).
     * A URL de busca é configurada via setUrlBusca().
     */
    public function crSelbusca(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'       => $this->nome,
            'id'         => $this->id,
            'data-busca' => $this->urlbusca,
            'class'      => "{$this->classep} form-control form-select selbusca selectpicker",
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();
        $this->field['placeholder'] = str_replace('Informe', 'Selecione', $this->field['placeholder']);

        return $this->fmtDisplay(
            form_dropdown($this->field, $this->opcoes, $this->selecionado)
        );
    }

    /**
     * Cria um select cujas opções dependem do valor de outro campo (campo pai).
     * Ao focar, valida se o campo pai foi preenchido antes de carregar as opções.
     */
    public function crDepende(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->objeto      = 'select';
        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'           => $this->nome,
            'id'             => $this->id,
            'data-container' => 'body',
            'data-busca'     => $this->urlbusca,
            'data-pai'       => $this->pai,
            'onfocus'        => "testa_dep('{$this->pai}')",
            'class'          => 'form-control form-select dependente selectpicker',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();

        if ($this->place !== '') {
            $this->opcoes = ['' => 'Escolha ' . $this->place] + $this->opcoes;
        }

        return $this->fmtDisplay(
            form_dropdown($this->field, $this->opcoes, $this->selecionado)
        );
    }

    /**
     * Cria um select dependente com seleção múltipla.
     */
    public function crDependeMultiplo(): string
    {
        $this->acertaId();
        $this->tipo        = 'select';
        $this->selecionado ??= $this->valor;

        $this->field = [
            'name'             => $this->nome . '[]',
            'id'               => $this->id . '[]',
            'data-container'   => 'body',
            'data-busca'       => $this->urlbusca,
            'data-pai'         => $this->pai,
            'onfocus'          => "testa_dep('{$this->pai}')",
            'multiple'         => 'multiple',
            'data-live-search' => 'true',
            'class'            => 'form-control form-select dependente show-tick selectpicker',
        ];

        if ($this->size === 0) {
            $this->size = -1;
        }

        $this->propriedades();

        if ($this->place !== '') {
            $this->opcoes = ['' => 'Escolha ' . $this->place] + $this->opcoes;
        }

        $extras = 'size=1; data-actions-box="true"; data-size="2"; ' .
            'data-selected-text-format="count > 1"; ' .
            'style="height:auto;overflow-y:auto;max-height:100px;";';

        return $this->fmtDisplay(
            form_multiselect($this->field, $this->opcoes, $this->selecionado, $extras)
        );
    }

    // =========================================================================
    // Campos de upload
    // =========================================================================

    /**
     * Cria um campo de upload de imagem com preview inline.
     * A imagem é exibida abaixo do botão de seleção após o upload.
     */
    public function crImagem(): string
    {
        $this->acertaId();

        $this->field = [
            'name'           => $this->nome,
            'id'             => $this->id,
            'data-folder'    => $this->pasta,
            'data-file-type' => '.jpg',
            'accept'         => '.jpg',
            'style'          => 'display:none',
            'class'          => '',
        ];

        $groupant = '';
        $grouppos = '';

        if (! $this->leitura) {
            $groupant .= "<label id='lbl_{$this->id}' class='btn btn-primary'
                            style='white-space: normal;width:{$this->size}px;padding:0.8em;'
                            for='{$this->id}'
                            data-mdb-toggle='tooltip' data-mdb-placement='bottom'
                            title='A imagem será redimensionada para {$this->size}x{$this->largura} proporcionalmente'>
                            <i class='fas fa-image'></i>
                            Clique para selecionar imagem de " . esc($this->label);
        }

        $groupant .= "<div id='view_img_{$this->nome}' class='show img-thumbnail'>";
        $groupant .= "<img id='img_{$this->nome}' src='" . esc($this->valor) . "'
                        for='{$this->id}' class='img-thumbnail sempadding' alt=''
                        style='width:{$this->size}px;' />";
        $groupant .= '</div>';

        if (! $this->leitura) {
            $grouppos .= '</label>';
        }

        $this->propriedades();

        return $this->fmtDisplay(form_upload($this->field, $this->valor), $groupant, $grouppos);
    }

    /**
     * Cria um campo de upload de arquivo genérico (PDF, DOCX, etc.).
     * Exibe o nome do arquivo e um link de visualização após o upload.
     */
    public function crArquivo(): string
    {
        $this->acertaId();

        $tipoArq = $this->tipoArq !== '' ? $this->tipoArq : '.pdf,.docx';

        $this->field = [
            'name'           => $this->nome,
            'id'             => $this->id,
            'data-file-type' => $tipoArq,
            'accept'         => $tipoArq,
            'style'          => 'display:none',
            'class'          => '',
        ];

        $groupant = '';
        $grouppos = '';

        if (! $this->leitura) {
            $textoBtn = $this->valor === ''
                ? "<i class='fas fa-file'></i> Clique para selecionar Arquivo de " . esc($this->label)
                : "<i class='fas fa-file'></i> Clique para SUBSTITUIR o Arquivo de " . esc($this->label);

            $groupant .= "<label id='lbl_{$this->id}' class='btn btn-primary'
                            style='white-space: normal;width:{$this->size}px;padding:0.8em;'>
                            {$textoBtn}";

            $grouppos = 'arquivo'; // Sinaliza para fmtDisplay usar renderizarSuffixArquivo()
        }

        $this->propriedades();

        return $this->fmtDisplay(form_upload($this->field, $this->valor), $groupant, $grouppos);
    }
}
