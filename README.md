# Laravel PDF/A Conversion API

Uma API RESTful para conversão de documentos PDF para formato PDF/A, com sistema de rate limiting por IP e solicitações de expansão de limite.

## 🚀 Funcionalidades

### Conversão PDF

-   ✅ Conversão de PDF para PDF/A usando Ghostscript
-   ✅ Suporte a múltiplos arquivos (até 5 por vez)
-   ✅ Rate limiting diário por IP
-   ✅ Download de arquivos convertidos
-   ✅ Histórico de conversões
-   ✅ Estatísticas de uso

### Sistema de Expansão

-   ✅ Solicitação de expansão de limite por email
-   ✅ Aprovação/rejeição por administrador
-   ✅ Notificações por email
-   ✅ Histórico de solicitações

### Validação

-   ✅ Validação de compatibilidade PDF/A
-   ✅ Verificação de conformidade PDF/A
-   ✅ Estimativa de tempo de processamento
-   ✅ Informações de formatos suportados

## 📋 Endpoints da API

### Base URL: `/api/v1`

#### 🔄 Conversões PDF

| Método | Endpoint             | Descrição                      |
| ------ | -------------------- | ------------------------------ |
| `POST` | `/pdf/convert`       | Converter PDF para PDF/A       |
| `GET`  | `/pdf/download/{id}` | Download do arquivo convertido |
| `GET`  | `/pdf/status/{id}`   | Status de uma conversão        |
| `GET`  | `/pdf/history`       | Histórico de conversões        |
| `GET`  | `/pdf/stats`         | Estatísticas do usuário        |

#### 📈 Expansão de Limite

| Método   | Endpoint             | Descrição                     |
| -------- | -------------------- | ----------------------------- |
| `POST`   | `/expansion/request` | Solicitar expansão de limite  |
| `GET`    | `/expansion/status`  | Status da solicitação atual   |
| `GET`    | `/expansion/history` | Histórico de solicitações     |
| `GET`    | `/expansion/info`    | Informações sobre expansão    |
| `DELETE` | `/expansion/cancel`  | Cancelar solicitação pendente |

#### ✅ Validação

| Método | Endpoint             | Descrição                      |
| ------ | -------------------- | ------------------------------ |
| `POST` | `/validate/pdf`      | Validar PDF para conversão     |
| `POST` | `/validate/pdf-a`    | Verificar conformidade PDF/A   |
| `POST` | `/validate/estimate` | Estimar tempo de processamento |
| `GET`  | `/validate/formats`  | Formatos suportados            |

#### 🏥 Sistema

| Método | Endpoint  | Descrição           |
| ------ | --------- | ------------------- |
| `GET`  | `/health` | Status da API       |
| `GET`  | `/docs`   | Documentação da API |

## 🔧 Arquitetura

### Padrões Implementados

-   **MVC + Services**: Separação clara de responsabilidades
-   **Form Requests**: Validação centralizada e reutilizável
-   **API Resources**: Formatação consistente de respostas
-   **Repository Pattern**: Abstração de acesso a dados nos Services
-   **Rate Limiting**: Controle de uso por IP

### Estrutura de Código

```
app/
├── Http/
│   ├── Controllers/Api/          # Controllers limpos, apenas orquestração
│   ├── Requests/                 # Validação de entrada
│   └── Resources/                # Formatação de saída
├── Models/                       # Eloquent Models
├── Services/                     # Lógica de negócio
└── config/pdfa.php              # Configurações da aplicação
```

### Controllers Refatorados

Os controllers agora estão muito mais limpos:

#### ✅ **Antes** (Controller "gordo"):

```php
public function store(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|max:255',
        // ... 20+ linhas de validação
    ]);

    if ($validator->fails()) {
        return response()->json([...], 422);
    }

    // Lógica de negócio misturada
    $usage = DailyUsage::getForIpToday($ipAddress);
    // ... mais código
}
```

#### ✅ **Depois** (Controller "magro"):

```php
public function store(ExpansionRequest $request): JsonResponse
{
    try {
        $data = $this->expansionService->createExpansionRequest([
            'email' => $request->email,
            'name' => $request->name,
            // ...
        ]);

        return response()->json([
            'success' => true,
            'data' => new UserExpansionRequestResource($data)
        ], 201);
    } catch (Exception $e) {
        return $this->handleException($e);
    }
}
```

### Form Requests

-   **PdfConversionRequest**: Validação para uploads de PDF
-   **ExpansionRequest**: Validação para solicitações de expansão
-   **PdfValidationRequest**: Validação dinâmica baseada na rota

### API Resources

-   **PdfConversionResource**: Formatação de conversões
-   **UserExpansionRequestResource**: Formatação de solicitações
-   **DailyUsageResource**: Formatação de informações de uso
-   **PdfConversionCollection**: Listagem com estatísticas

## 📊 Benefícios da Refatoração

### 🎯 **Separation of Concerns**

-   Controllers: Apenas orquestração
-   Services: Lógica de negócio
-   Requests: Validação de entrada
-   Resources: Formatação de saída

### 🔄 **Reutilização**

-   Form Requests reutilizáveis em diferentes endpoints
-   Resources consistentes em toda API
-   Services podem ser usados por outros controllers

### 🧪 **Testabilidade**

-   Cada componente pode ser testado isoladamente
-   Mocks mais fáceis nos Services
-   Validação testável independentemente

### 📝 **Manutenibilidade**

-   Código mais legível e organizado
-   Mudanças isoladas em cada camada
-   Documentação automática via Resources

### 🚀 **Performance**

-   Validação otimizada nos Form Requests
-   Formatação lazy nos Resources
-   Cache de validações complexas

## ⚙️ Configuração

### Variáveis de Ambiente (.env)

```env
# PDF/A Settings
PDFA_DEFAULT_DAILY_LIMIT=10
PDFA_MAX_FILE_SIZE=10240
PDFA_PROCESSING_TIMEOUT=300
PDFA_STORAGE_PATH=pdf_conversions

# Ghostscript Path
GHOSTSCRIPT_PATH=/usr/bin/gs

# Admin Email
ADMIN_EMAIL=admin@example.com
```

### Configuração (config/pdfa.php)

```php
return [
    'default_daily_limit' => env('PDFA_DEFAULT_DAILY_LIMIT', 10),
    'max_file_size' => env('PDFA_MAX_FILE_SIZE', 10240), // KB
    'processing_timeout' => env('PDFA_PROCESSING_TIMEOUT', 300), // seconds
    'storage_path' => env('PDFA_STORAGE_PATH', 'pdf_conversions'),
    'ghostscript_path' => env('GHOSTSCRIPT_PATH', '/usr/bin/gs'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
];
```

## 🔐 Rate Limiting

-   **Limite padrão**: 10 conversões por dia por IP
-   **Expansão**: Até 10.000 conversões por dia
-   **Reset**: Diário às 00:00 UTC
-   **API calls**: 60 requests por minuto por IP

## 📦 Dependências

-   **Laravel 11**: Framework base
-   **Spatie PDF**: Manipulação de PDFs
-   **Ghostscript**: Conversão PDF/A
-   **PostgreSQL/MySQL**: Banco de dados flexível

## 🛠️ Instalação

### 1. Clone o projeto

```bash
git clone <url-do-repositorio>
cd laravel_pdfa_backend
```

### 2. Install dependências

```bash
composer install
```

### 3. Configure ambiente

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure banco de dados

```bash
# Configure as variáveis no .env
php artisan migrate
```

### 5. Instale Ghostscript

```bash
# macOS
brew install ghostscript

# Ubuntu/Debian
sudo apt-get install ghostscript

# CentOS/RHEL
sudo yum install ghostscript
```

### 6. Configure storage

```bash
php artisan storage:link
```

## 🧪 Testing

```bash
# Rodar todos os testes
php artisan test

# Testar endpoint específico
curl -X POST http://localhost/api/v1/pdf/convert \
  -F "files[]=@document.pdf"

# Verificar rotas
php artisan route:list | grep api
```

## 📈 Monitoramento

-   Logs estruturados para auditoria
-   Métricas de conversão e uso
-   Alertas de limite de recursos
-   Dashboard de estatísticas

## 🔍 Exemplos de Uso

### Converter PDF

```bash
curl -X POST http://localhost/api/v1/pdf/convert \
  -H "Content-Type: multipart/form-data" \
  -F "files[]=@document1.pdf" \
  -F "files[]=@document2.pdf"
```

### Verificar status

```bash
curl -X GET http://localhost/api/v1/pdf/status/123
```

### Solicitar expansão

```bash
curl -X POST http://localhost/api/v1/expansion/request \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "name": "João Silva",
    "company": "Minha Empresa Ltda",
    "justification": "Preciso processar documentos para auditoria fiscal..."
  }'
```

---

**Desenvolvido com** ❤️ **usando Laravel e boas práticas de arquitetura**
