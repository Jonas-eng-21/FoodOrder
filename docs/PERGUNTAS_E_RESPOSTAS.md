# Perguntas e respostas para a defesa técnica

Respostas baseadas no código e nos registros do projeto. Detalhes: [Padrões de projeto](PADROES_DE_PROJETO.md), [Arquitetura](ARQUITETURA.md), [Validação e testes](VALIDACAO_E_TESTES.md).

## Perguntas gerais

**Qual problema o FoodOrder resolve?** Organiza o fluxo de um pedido de lanchonete: montar o item com complementos, calcular o pagamento por método, simular a cobrança, acompanhar o pedido até a entrega e registrar histórico e avisos internos. É um MVP acadêmico, não um sistema comercial.

**Por que escolheram esse domínio?** Ele reúne de forma pequena e familiar os cinco tipos de variação que os padrões tratam: combinações de opcionais, regras intercambiáveis, interfaces incompatíveis, ciclo de vida e reações a eventos. Assim a discussão fica nos padrões, não na regra de negócio.

**Por que Laravel?** Entrega MVC, Eloquent, validação, injeção de dependência e testes prontos, de modo que o código autoral seja o que importa: as regras e os padrões. A organização `app/Patterns/` separa o que é do projeto do que é do framework.

**Por que SQLite?** Persistência local sem servidor, simples de reproduzir em Docker (arquivo em volume). O custo é um único escritor por vez, tratado com transações `BEGIN IMMEDIATE`.

**Por que monólito?** Um operador, um banco, poucas classes e a necessidade de atomicidade entre pagamento, estado, histórico e notificações. Microsserviços exigiriam consistência distribuída sem benefício (KISS e YAGNI).

**Por que Docker?** Para que o único requisito no host seja Docker e Compose: o clone limpo sobe sem PHP, Composer ou Node (RNF01, RNF07).

**Por que separar desenvolvimento e produção?** Para demonstrar uma imagem imutável e endurecida (PHP-FPM + Nginx, `--no-dev`, raiz somente leitura, `APP_DEBUG=false`) sem arriscar os dados do ambiente de desenvolvimento. Redes, volumes, bancos, portas e chaves são distintos, com guardas nos entrypoints.

**Como os cinco padrões se relacionam?** Em um pagamento: o Decorator já produziu o subtotal; o State confirma que o pedido pode ser pago; o Strategy calcula o total; o Adapter processa a cobrança simulada; o State define o próximo estado; o Observer registra histórico e notificações; tudo em uma transação. Ver [sequência](diagrams/payment-sequence.md).

**Os padrões foram usados por necessidade real ou só para a disciplina?** A disciplina exige os cinco, e o domínio foi escolhido para que cada um resolvesse um problema concreto (ver o "problema" de cada padrão). Alguns são mais pesados do que um MVP exigiria (por exemplo, seis classes de estado ou três decorators quase idênticos); isso é um trade-off assumido por finalidade acadêmica.

## Decorator

**Por que Decorator em vez de herança?** Herança exigiria uma subclasse por combinação e cresceria exponencialmente a cada complemento. Com decorators, cada extra é uma classe e a combinação é feita em tempo de execução.

**Qual é o Component? E o Concrete Component?** `MenuItem` (interface com `priceCents()` e `description()`) e `BaseMenuItem` (produto original).

**Como ocorre o encadeamento?** `OrderService::create()` parte do `BaseMenuItem` e, para cada extra selecionado, reatribui `$item = new XDecorator($item, $extra)`. Cada decorator chama o `parent` (que delega ao `inner`) e soma sua parte.

**Como o preço é calculado?** `priceCents()` do decorator externo = `parent::priceCents()` (preço do interno) + preço do extra. Para Hambúrguer + Bacon + Queijo: 2000 + 400 + 300 = 2700.

**Como adicionar um novo complemento?** Nova classe que estende `MenuItemDecorator`, uma entrada em `config/foodorder.php` e o mapeamento em `OrderService::DECORATORS` (um teste falha se um extra do catálogo não tiver decorator).

**O componente original é modificado?** Não. Os objetos são imutáveis (propriedades `readonly`) e o teste `test_decorating_does_not_modify_the_original_component` verifica isso.

**Diferença entre Decorator e Adapter?** O Decorator mantém a interface e acrescenta comportamento; o Adapter converte uma interface em outra.

## Strategy

**Qual família de algoritmos foi encapsulada?** O cálculo do total por forma de pagamento: PIX (5% de desconto), cartão (integral) e dinheiro (integral + troco).

**Qual é o Context?** `PaymentCalculator`.

**Como ocorre a seleção da estratégia?** O `PaymentCalculator` recebe um mapa `método → estratégia` (montado no `AppServiceProvider`) e usa a chave do método escolhido; método desconhecido lança `InvalidPaymentException`.

**Por que não apenas `if` ou `switch`?** Um `switch` central concentraria todos os algoritmos e obrigaria a editar código testado a cada novo método. Aqui cada algoritmo é uma classe pequena, testada isoladamente, e o Context só delega.

**Como adicionar um novo método de pagamento?** Nova `PaymentStrategy`, registro no provider, valor no enum `PaymentMethod` e um gateway (`PaymentGateway`) correspondente.

**Como é garantida a precisão monetária?** Tudo em centavos inteiros; o PIX usa `intdiv($subtotal * 95 + 50, 100)`; há verificação de overflow; `PaymentQuote` valida `total + desconto = subtotal`.

**Diferença entre Strategy e State?** Mesma forma (interface com implementações trocáveis), intenções diferentes. No Strategy o cliente escolhe o algoritmo de fora e ele não se transforma; no State o objeto muda de estado conforme as operações e cada estado devolve o próximo.

## Adapter

**Quais interfaces são incompatíveis?** `LegacyPixGateway::sendPix(int): array` e `LegacyCardGateway::charge(string): string`, e nenhuma delas é o `PaymentGateway::charge(int): PaymentResult` que o serviço espera.

**Qual é o Target? Quais são os Adaptees?** Target: `PaymentGateway`. Adaptees: `LegacyPixGateway` e `LegacyCardGateway`.

**O que os adapters realmente adaptam?** O argumento (centavos → string decimal `"25.65"`, no cartão) e a resposta (array/string legados → `PaymentResult`), tratando rejeições e respostas inválidas como não aprovadas.

**Por que dinheiro não usa adapter?** `CashPaymentGateway` já implementa o Target; não há interface incompatível, e um adapter seria uma camada inútil (YAGNI).

**Diferença entre Adapter e Decorator?** Adapter muda a interface para torná-la compatível; Decorator preserva a interface e adiciona responsabilidade.

**Diferença entre Adapter e Facade?** Facade oferece uma interface simplificada para um subsistema; Adapter faz uma classe existente se encaixar na interface esperada. Cada adapter do FoodOrder envolve uma classe só.

**Como substituir um gateway simulado?** Criar outra implementação de `PaymentGateway` (ou adapter de outra API) e registrá-la no `AppServiceProvider`. O `PaymentService` não muda. Hoje todos são simuladores locais, sem integração financeira.

## State

**Por que não apenas um enum?** O enum guarda o valor persistido; as regras ficariam em `switch` espalhados. Com as classes, cada estado conhece só suas saídas e o resto é rejeitado por herança.

**Qual é o Context?** `Order`.

**Onde estão as regras de transição?** Nas classes `PendingState`, `PaidState`, `PreparingState` e `ReadyState`; `AbstractOrderState` rejeita tudo o que não é sobrescrito.

**Como o estado é reidratado?** `Order::state()` converte o `status` persistido na classe correspondente (`match`).

**Como impedir transições inválidas?** O estado atual lança `InvalidOrderTransitionException`; o backend aplica isso mesmo que o botão não exista (teste de POST direto).

**O que são estados terminais?** `delivered` e `cancelled`: nenhuma operação é permitida.

**Como impedir pagamento depois do cancelamento?** `CancelledState` herda a rejeição de `pay()`; o `PaymentService` consulta o estado antes de calcular ou cobrar, e a releitura na transação evita agir sobre estado antigo (teste de concorrência pagamento × cancelamento).

**Diferença entre State e Strategy?** Veja acima: a intenção, não a forma.

## Observer

**Qual é o Subject?** `OrderEventPublisher`.

**Como os observers são inscritos?** Em `AppServiceProvider`, que cria o publisher (singleton) e chama `subscribe()` para os três.

**Como remover um observer?** `unsubscribe($observer)` (teste `test_an_unsubscribed_observer_stops_receiving_events`).

**Como evitar inscrições duplicadas?** O publisher indexa por `spl_object_id`, então a mesma instância não entra duas vezes. Duas instâncias da mesma classe são tratadas como observers distintos; por isso o banco também impõe unicidade de histórico e notificações.

**Por que três observadores?** São três efeitos independentes: histórico, aviso ao cliente e aviso à cozinha. A cozinha só age em `paid` e `ready`; essa decisão fica no próprio observer.

**Como adicionar um novo observador?** Implementar `OrderObserver` e adicionar um `subscribe()` no provider; o publisher, o `Order` e os serviços não mudam.

**O publisher conhece os observadores concretos?** Não; conhece só a interface `OrderObserver`.

**O que acontece se um observer falhar?** A exceção propaga, os seguintes não rodam e a transação é revertida (pagamento, estado, histórico e notificações); a operação pode ser repetida depois (`OrderObserverRollbackTest`).

**Por que o Observer é síncrono?** Para que os efeitos sejam atômicos com a transição, sem filas, e porque o sistema é um monólito local.

**Diferença entre Observer e mensageria distribuída?** O Observer é em memória, no mesmo processo e transação; mensageria envolve broker, entrega assíncrona e garantias próprias (reentrega, ordenação, idempotência). O FoodOrder não precisa disso. E as "notificações" são registros no banco, não mensagens reais.

## Engenharia de software

**Onde SOLID foi aplicado?** Responsabilidade única (cada decorator, estratégia, estado e observer faz uma coisa); aberto/fechado (novos extras, métodos, observers sem alterar o núcleo); Liskov (todas as implementações substituem suas interfaces); segregação (interfaces pequenas); inversão de dependência (serviços dependem de `PaymentGateway`, `PaymentStrategy`, `OrderObserver`). Aplicado de forma pragmática.

**Como KISS e YAGNI influenciaram?** Monólito, SQLite, sem filas, sem repositórios genéricos, sem carrinho, sem adapter para dinheiro e sem funcionalidades fora do escopo.

**Como o TDD foi utilizado?** Os testes de cada US foram escritos antes (Red), seguidos da implementação mínima (Green) e do refinamento (Refactor), registrados no histórico local de desenvolvimento.

**Diferença entre testes unitários e Feature?** Unitários exercitam classes isoladas (por exemplo, as estratégias e a matriz de estados); Feature exercitam HTTP, banco e serviços integrados.

**Como foram testadas as transações?** Com falhas injetadas (por exemplo, falha de observer ou de gravação) verificando que nada permanece, e com testes de concorrência usando **dois processos PHP reais** sobre um SQLite em arquivo.

**Como são evitadas condições de corrida?** Transação `BEGIN IMMEDIATE` (o segundo processo espera o lock), releitura do pedido dentro da transação, State rejeitando a segunda operação e UNIQUEs como última barreira.

**Por que `lockForUpdate()` isoladamente não seria suficiente no SQLite?** O SQLite não tem `SELECT ... FOR UPDATE`; a gramática SQLite do Laravel ignora a cláusula. O projeto não usa `lockForUpdate()`: o bloqueio vem do modo `IMMEDIATE` da transação.

**Como funciona `BEGIN IMMEDIATE`?** Adquire o bloqueio de escrita no início da transação (em vez de na primeira escrita). Quem chega depois espera até o `busy_timeout` (5000 ms) e, ao entrar, relê o estado já atualizado.

**Como são evitados pagamentos duplicados?** Estado `pending` exigido na releitura, UNIQUE em `payments.order_id` e a transação; o segundo pagamento é rejeitado antes de chamar o gateway.

**Como é garantida a integridade dos históricos?** Histórico e notificações são gravados na mesma transação da mudança de estado, com UNIQUE (`order_id`, `new_status`) e (`order_id`, `event_key`, `audience`).

**O projeto está preparado para exposição pública?** Não. Não há autenticação nem TLS; foi feito para execução local ou rede privada controlada.

**Quais são as principais limitações?** Sem autenticação e TLS, pagamento simulado, um produto por pedido, sem paginação nem filtros, horários em UTC, backup/restauração manuais, CSP com `unsafe-inline`, sessões em tmpfs na produção, e sem leitor de tela nem teste automatizado de contraste. Ver [limitações](VALIDACAO_E_TESTES.md#limitações-conhecidas-e-classificação).

**O que poderia ser desenvolvido em versões futuras?** Opcionalmente: autenticação, carrinho com vários itens, paginação e filtros, notificações reais por um canal externo, CI e TLS. Nenhum item está comprometido nem faz parte do MVP.

Voltar: [índice](README.md)
