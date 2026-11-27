# Documentação do FoodOrder

Ponto de entrada da documentação técnica e acadêmica. O [README do repositório](../README.md) traz o início rápido.

| Documento | Conteúdo |
|---|---|
| [Visão geral](VISAO_GERAL.md) | Problema, objetivos, escopo, tecnologias e limites |
| [Requisitos e regras de negócio](REQUISITOS_E_REGRAS_DE_NEGOCIO.md) | RF01–RF09, RNF01–RNF08, RN01–RN10 e backlog das 10 User Stories |
| [Arquitetura](ARQUITETURA.md) | Monólito MVC, responsabilidades, diretórios e decisões |
| [Modelagem de dados](MODELAGEM_DE_DADOS.md) | Dicionário de dados, relacionamentos, snapshots e integridade |
| [Padrões de projeto](PADROES_DE_PROJETO.md) | Decorator, Strategy, Adapter, State e Observer, com código, testes e demonstração |
| [Matriz de rastreabilidade](MATRIZ_DE_RASTREABILIDADE.md) | Requisitos → classes → testes |
| [Validação e testes](VALIDACAO_E_TESTES.md) | Estratégia, resultados reais, concorrência, smoke e limitações |
| [Ambientes Docker](AMBIENTES_DOCKER.md) | Desenvolvimento e produção local, `dcp.sh`, backup |
| [Guia de apresentação](GUIA_DE_APRESENTACAO.md) | Roteiro, slides sugeridos e demonstração |
| [Perguntas e respostas](PERGUNTAS_E_RESPOSTAS.md) | Preparação para a defesa técnica |

## Diagramas

Todos em Mermaid (renderizados pelo GitHub), em `docs/diagrams/`:

| Tipo | Diagramas |
|---|---|
| C4 Model | [Contexto](diagrams/c4-context.md) · [Contêineres](diagrams/c4-container.md) · [Componentes](diagrams/c4-component.md) |
| Casos de uso e domínio | [Casos de uso](diagrams/use-cases.md) · [Modelo de domínio](diagrams/domain-model.md) |
| Estados e dados | [Estados do pedido](diagrams/order-state.md) · [ER do banco](diagrams/database-erd.md) |
| UML de classes dos padrões | [Decorator](diagrams/decorator-class.md) · [Strategy](diagrams/strategy-class.md) · [Adapter](diagrams/adapter-class.md) · [State](diagrams/state-class.md) · [Observer](diagrams/observer-class.md) |
| Sequência | [Criação do pedido](diagrams/create-order-sequence.md) · [Pagamento](diagrams/payment-sequence.md) · [Ciclo de vida](diagrams/lifecycle-sequence.md) |
| Implantação | [Docker dev e prod](diagrams/docker-deployment.md) |

O nível 4 do C4 (código) não foi duplicado: os diagramas de classes dos cinco padrões cumprem esse papel.

## Observação

Os documentos descrevem o código efetivamente implementado. O contexto interno de desenvolvimento (`context/`) é local e não faz parte do repositório.
