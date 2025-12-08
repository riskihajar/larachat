# AWS Bedrock APIs: Converse vs Model-Specific (Anthropic Messages)

## Overview

AWS Bedrock menyediakan **3 cara berbeda** untuk invoke models:

1. **InvokeModel** - Model-specific format (original, deprecated approach)
2. **Anthropic Messages API** - Claude-specific format via Bedrock
3. **Converse API** - Universal standardized format (recommended)

Mari kita bahas **2 API utama** yang relevan untuk streaming:

---

## 1. Anthropic Messages API (Model-Specific)

### **Apa itu?**

API khusus untuk Claude models yang menggunakan **native Anthropic format** melalui AWS Bedrock.

### **Endpoints**

```
Non-streaming:
POST /model/{modelId}/invoke

Streaming:
POST /model/{modelId}/invoke-with-response-stream
```

### **Request Format**

```json
{
    "anthropic_version": "bedrock-2023-05-31",
    "max_tokens": 4096,
    "messages": [
        {
            "role": "user",
            "content": "Hello!"
        }
    ],
    "system": "You are a helpful assistant",
    "temperature": 0.7
}
```

### **Response Format (Streaming)**

**Binary Event Stream** dengan struktur:
```
[Prelude: 8 bytes]
[Prelude CRC: 4 bytes]
[Headers: variable] → Contains :event-type = "chunk"
[Payload: variable] → JSON wrapped in base64 bytes field
[Message CRC: 4 bytes]
```

**Payload Content (after base64 decode):**
```json
{
    "type": "content_block_delta",
    "index": 0,
    "delta": {
        "type": "text_delta",
        "text": "Hello"
    }
}
```

### **Characteristics**

| Aspect | Detail |
|--------|--------|
| **Format** | Native Anthropic (sama dengan direct Anthropic API) |
| **Models** | Claude only |
| **Streaming** | Binary event stream format |
| **Features** | Full Claude features (thinking, tool use, etc.) |
| **Complexity** | High (binary parsing required) |
| **Who Uses** | larachat, developers wanting native Claude format |

### **Example (PHP - larachat)**

```php
// Request
$url = "https://bedrock-runtime.us-west-2.amazonaws.com/model/anthropic.claude-sonnet-4-20250514-v1:0/invoke-with-response-stream";

$payload = [
    'anthropic_version' => 'bedrock-2023-05-31',
    'max_tokens' => 4096,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!']
    ],
    'system' => 'You are helpful',
    'temperature' => 0.7,
];

// Response parsing
$chunk = json_decode($payload, true);
if (isset($chunk['bytes'])) {
    $decodedPayload = base64_decode($chunk['bytes']);
    $chunk = json_decode($decodedPayload, true);
}
$text = $chunk['delta']['text'];
```

---

## 2. Converse API (Universal Standard)

### **Apa itu?**

**Unified API** dari AWS Bedrock yang menyediakan **standardized interface** untuk semua foundation models (Claude, Llama, Mistral, etc.).

### **Endpoints**

```
Non-streaming:
POST /model/{modelId}/converse

Streaming:
POST /model/{modelId}/converse-stream
```

### **Request Format**

```json
{
    "messages": [
        {
            "role": "user",
            "content": [
                {
                    "text": "Hello!"
                }
            ]
        }
    ],
    "system": [
        {
            "text": "You are a helpful assistant"
        }
    ],
    "inferenceConfig": {
        "maxTokens": 4096,
        "temperature": 0.7,
        "topP": 0.9,
        "stopSequences": []
    },
    "toolConfig": {
        "tools": []
    },
    "guardrailConfig": {}
}
```

### **Response Format (Streaming)**

**Event Stream** dengan structured events:

#### **Event Types:**

1. **messageStart**
```json
{
    "messageStart": {
        "role": "assistant"
    }
}
```

2. **contentBlockStart**
```json
{
    "contentBlockStart": {
        "start": {
            "text": ""
        },
        "contentBlockIndex": 0
    }
}
```

3. **contentBlockDelta** (← TEXT CHUNKS)
```json
{
    "contentBlockDelta": {
        "delta": {
            "text": "Hello"
        },
        "contentBlockIndex": 0
    }
}
```

4. **contentBlockStop**
```json
{
    "contentBlockStop": {
        "contentBlockIndex": 0
    }
}
```

5. **messageStop**
```json
{
    "messageStop": {
        "stopReason": "end_turn"
    }
}
```

6. **metadata**
```json
{
    "metadata": {
        "usage": {
            "inputTokens": 20,
            "outputTokens": 100,
            "totalTokens": 120
        },
        "metrics": {
            "latencyMs": 1500
        }
    }
}
```

### **Characteristics**

| Aspect | Detail |
|--------|--------|
| **Format** | AWS universal standard |
| **Models** | All Bedrock models (Claude, Llama, Mistral, Cohere, etc.) |
| **Streaming** | Structured event stream (easier to parse) |
| **Features** | Tools, guardrails, inference profiles |
| **Complexity** | Medium (structured events) |
| **Who Uses** | prism-php/bedrock, vercel-ai, AWS best practice |

### **Example (PHP - prism-php/bedrock)**

```php
// Request (from PR #23)
$response = $client->invokeModelWithResponseStream([
    'modelId' => 'anthropic.claude-sonnet-4-20250514-v1:0',
    'contentType' => 'application/json',
    'accept' => 'application/json',
    'body' => json_encode([
        'messages' => [
            [
                'role' => 'user',
                'content' => [['text' => 'Hello!']]
            ]
        ],
        'system' => [['text' => 'You are helpful']],
        'inferenceConfig' => [
            'maxTokens' => 4096,
            'temperature' => 0.7,
        ],
    ]),
]);

// Response parsing (structured events)
foreach ($response['stream'] as $event) {
    if (isset($event['contentBlockDelta'])) {
        $text = $event['contentBlockDelta']['delta']['text'];
        echo $text;
    }
}
```

---

## Comparison: Anthropic Messages vs Converse

| Aspect | Anthropic Messages API | Converse API |
|--------|----------------------|--------------|
| **Endpoint** | `/invoke-with-response-stream` | `/converse-stream` |
| **Format** | Anthropic-specific | AWS universal |
| **Models** | Claude only | All Bedrock models |
| **Request Structure** | `anthropic_version`, `max_tokens` | `inferenceConfig`, `system` array |
| **Response Format** | Binary event stream + base64 | Structured JSON events |
| **Parsing Complexity** | High (binary + base64) | Medium (JSON events) |
| **Feature Parity** | Full Claude features | Standardized features |
| **Best For** | Claude-only apps | Multi-model apps |
| **AWS Recommendation** | ❌ Legacy approach | ✅ Recommended |
| **larachat Uses** | ✅ Yes | ❌ No |
| **prism-php/bedrock Uses** | ❌ No (non-streaming only) | ✅ Yes (PR #23, #37) |
| **vercel-ai Uses** | ❌ No | ✅ Yes |

---

## Why AWS Recommends Converse API?

### **1. Unified Interface**

```
Before (Model-Specific):
- Anthropic format for Claude
- Meta format for Llama
- Cohere format for Command
- Different parsing for each!

After (Converse):
- One format for ALL models
- Switch models by changing modelId only
- Consistent code across providers
```

### **2. Future-Proof**

```
New models automatically supported:
- No code changes needed
- Just update modelId
- Same request/response structure
```

### **3. Enhanced Features**

```
Converse-only features:
- Inference profiles (cross-region)
- Advanced guardrails
- Prompt caching (upcoming)
- Performance metrics
```

### **4. Better DX (Developer Experience)**

```
Simpler integration:
- Structured JSON events (not binary)
- Consistent naming
- Better documentation
- Multi-language SDKs
```

---

## Migration Path: Anthropic Messages → Converse

### **larachat Current (Anthropic Messages):**

```php
$url = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$model}/invoke-with-response-stream";

$payload = [
    'anthropic_version' => 'bedrock-2023-05-31',
    'max_tokens' => 4096,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ],
    'system' => 'You are helpful',
];
```

### **Migrated to Converse:**

```php
$url = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$model}/converse-stream";

$payload = [
    'messages' => [
        [
            'role' => 'user',
            'content' => [['text' => 'Hello']]
        ]
    ],
    'system' => [
        ['text' => 'You are helpful']
    ],
    'inferenceConfig' => [
        'maxTokens' => 4096,
        'temperature' => 0.7,
    ],
];
```

### **Benefits of Migration:**

1. ✅ **Simpler parsing** - No binary event stream, no base64 decoding
2. ✅ **Structured events** - `contentBlockDelta` instead of custom parsing
3. ✅ **Multi-model ready** - Easy to add Llama, Mistral, etc.
4. ✅ **AWS SDK support** - Better maintained
5. ✅ **Lower maintenance** - Less custom code

---

## PR #23 vs PR #37 in prism-php/bedrock

### **PR #23: Initial Converse Streaming**

```
Added:
- ConverseStreamHandler
- HandlesStream trait
- Basic streaming support
- Stream event processing

Focus: Foundation for streaming
Status: Merged
```

### **PR #37: Enhanced Streaming Features**

```
Added:
- Extended event types
- Tool use in streaming
- Reasoning/thinking support
- Better error handling
- More test coverage

Focus: Production-ready features
Status: Merged
```

### **Combined Result:**

```php
// Now prism-php/bedrock has full Converse streaming:
$response = Prism::text()
    ->using('bedrock', 'anthropic.claude-sonnet-4')
    ->withPrompt('Hello')
    ->stream();  // ← Returns Generator

foreach ($response as $chunk) {
    echo $chunk;  // Progressive text
}
```

---

## Use Case Decision Tree

### **Should I use Anthropic Messages API?**

```
✅ YES if:
- Claude-only application
- Already have Anthropic-specific code
- Need 100% Claude feature parity
- Educational/learning project
- Want native Anthropic format

❌ NO if:
- Need multi-model support
- Starting new project
- Want AWS recommended approach
- Need lower maintenance
```

### **Should I use Converse API?**

```
✅ YES if:
- Multi-model application
- Starting new project
- Want AWS best practices
- Need future-proof solution
- Want easier parsing

❌ NO if:
- Need Claude-specific features not in Converse
- Already invested heavily in Anthropic Messages
```

---

## Real-World Examples

### **1. larachat (Anthropic Messages API)**

**Use Case:** Real-time chat with Claude
**Reason:** Built before Converse was popular, Claude-optimized
**Implementation:** Custom binary parser

```php
// Custom binary parsing
$totalLength = unpack('N', substr($prelude, 0, 4))[1];
$chunk = json_decode(base64_decode($payload['bytes']), true);
yield $chunk['delta']['text'];
```

### **2. prism-php/bedrock (Converse API)**

**Use Case:** Multi-model AI orchestration
**Reason:** Support all Bedrock models, AWS recommended
**Implementation:** Converse stream handler

```php
// Structured event processing
foreach ($stream as $event) {
    if (isset($event['contentBlockDelta'])) {
        yield $event['contentBlockDelta']['delta']['text'];
    }
}
```

### **3. vercel-ai (Converse API)**

**Use Case:** Universal AI SDK
**Reason:** Framework-agnostic, multi-provider
**Implementation:** Converse with binary codec

```typescript
// TransformStream pipeline
response.body.pipeThrough(
    new TransformStream({
        transform(chunk) {
            const event = parseConverseEvent(chunk);
            if (event.type === 'contentBlockDelta') {
                controller.enqueue(event.delta.text);
            }
        }
    })
);
```

---

## Kesimpulan

### **Converse API adalah:**

1. **Universal Standard** - One format for all Bedrock models
2. **AWS Recommended** - Best practice approach
3. **Future-Proof** - Ready for new models
4. **Easier to Parse** - Structured JSON events vs binary
5. **Production-Ready** - Battle-tested by AWS

### **Anthropic Messages API adalah:**

1. **Claude-Specific** - Native Anthropic format
2. **Legacy Approach** - Still supported but not recommended
3. **Binary Complex** - Requires custom parsing
4. **Feature Complete** - Full Claude capabilities
5. **Educational** - Good for learning AWS internals

### **Recommendation:**

**New Projects:**
→ Use **Converse API** (`/converse-stream`)

**Existing Claude Apps:**
→ Consider migrating to Converse for long-term benefits

**Learning AWS:**
→ Study both! larachat for binary parsing, Converse for best practices

---

## References

### **Official AWS Documentation**
- [Converse API](https://docs.aws.amazon.com/bedrock/latest/APIReference/API_runtime_Converse.html)
- [ConverseStream API](https://docs.aws.amazon.com/bedrock/latest/APIReference/API_runtime_ConverseStream.html)
- [Conversation Inference Guide](https://docs.aws.amazon.com/bedrock/latest/userguide/conversation-inference.html)

### **Implementations**
- [prism-php/bedrock PR #23](https://github.com/prism-php/bedrock/pull/23) - Initial Converse streaming
- [prism-php/bedrock PR #37](https://github.com/prism-php/bedrock/pull/37) - Enhanced streaming features
- [larachat AWS Bedrock Streaming](./AWS_BEDROCK_STREAMING.md) - Anthropic Messages API implementation

---

**Author:** Generated during API comparison analysis
**Date:** December 2025
**Status:** Comprehensive guide complete ✅
