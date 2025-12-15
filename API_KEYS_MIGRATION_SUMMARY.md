# API Keys & Configuration Migration Summary

## Overview
Sensitive API keys and configuration parameters have been moved from hardcoded values to environment variables in the `.env` file for better security and maintainability.

## Changes Made

### 1. **`.env` File** ✅
Added the following sensitive configuration:

```
# Wandb Configuration
WANDB_API_KEY=9a2dd71fea975e82e9f4efcf5cabe5ded3b52326
WANDB_PROJECT=Flowise_LLM_Course_Builder_Eval_QUIZ

# Flowise Configuration
FLOWISE_API_HOST=https://cloud.flowiseai.com
FLOWISE_CHATFLOW_ID_QUIZ=650969ed-b4b4-4e57-b74d-a87c7520c846
FLOWISE_CHATFLOW_ID_COURSE=0ca67919-d561-4558-993c-0cc269ca19b6

# API Timeouts (in seconds)
FLOWISE_TIMEOUT=900
FLOWISE_CONNECT_TIMEOUT=60
```

### 2. **`.env.example` File** ✅
Updated with placeholder values for all API configuration:

```
WANDB_API_KEY=your_wandb_api_key_here
WANDB_PROJECT=Flowise_LLM_Course_Builder_Eval_QUIZ
FLOWISE_API_HOST=https://cloud.flowiseai.com
FLOWISE_CHATFLOW_ID_QUIZ=your_flowise_quiz_chatflow_id_here
FLOWISE_CHATFLOW_ID_COURSE=your_flowise_course_chatflow_id_here
FLOWISE_TIMEOUT=900
FLOWISE_CONNECT_TIMEOUT=60
```

### 3. **`PdfExtractController.php`** ✅
Replaced all hardcoded API keys with `env()` function calls:

#### Before:
```php
private string $wandbApiKey = '9a2dd71fea975e82e9f4efcf5cabe5ded3b52326';
private string $wandbProject = 'Flowise_LLM_Course_Builder_Eval_QUIZ';
$chatflowId1 = '650969ed-b4b4-4e57-b74d-a87c7520c846';
$chatflowId = '0ca67919-d561-4558-993c-0cc269ca19b6';
$apiHost = "https://cloud.flowiseai.com";
$response = Http::timeout(900)->connectTimeout(60)...
```

#### After:
```php
// Removed class properties entirely
$chatflowId1 = env('FLOWISE_CHATFLOW_ID_QUIZ');
$chatflowId = env('FLOWISE_CHATFLOW_ID_COURSE');
$apiHost = env('FLOWISE_API_HOST');
$response = Http::timeout(env('FLOWISE_TIMEOUT', 900))
            ->connectTimeout(env('FLOWISE_CONNECT_TIMEOUT', 60))...

// In logToWandb() method:
$metrics['api_key'] = env('WANDB_API_KEY');
$metrics['project_name'] = env('WANDB_PROJECT');
```

### 4. **`wandb_logger.py`** ✅
The Python file already supports reading API keys from the JSON payload passed by Laravel, so no changes required. The keys are now passed via:

```php
$metrics['api_key'] = env('WANDB_API_KEY');
$metrics['project_name'] = env('WANDB_PROJECT');
```

And then passed to the Python script through stdin.

## Security Benefits

✅ **No hardcoded credentials** in source code  
✅ **Easy environment-specific configuration** (dev/prod/staging)  
✅ **Complies with 12-factor app methodology**  
✅ **Credentials safe from accidental Git commits**  
✅ **Centralized configuration management**  
✅ **Easy rotation of API keys without code changes**

## Variables Summary

| Variable | Purpose | Used In |
|----------|---------|---------|
| `WANDB_API_KEY` | Weights & Biases API authentication | `logToWandb()` method |
| `WANDB_PROJECT` | Weights & Biases project name | `logToWandb()` method |
| `FLOWISE_API_HOST` | Flowise API base URL | Quiz & Course Flowise calls |
| `FLOWISE_CHATFLOW_ID_QUIZ` | Flowise chatflow for quiz generation | First Flowise HTTP request |
| `FLOWISE_CHATFLOW_ID_COURSE` | Flowise chatflow for course creation | Second Flowise HTTP request |
| `FLOWISE_TIMEOUT` | HTTP request timeout in seconds | Both Flowise API calls |
| `FLOWISE_CONNECT_TIMEOUT` | Connection timeout in seconds | Both Flowise API calls |

## Testing Checklist

- [ ] Verify `.env` file has all required variables
- [ ] Test `chatbotCourseBuilder` endpoint with valid PDF
- [ ] Confirm Flowise API calls work with `env()` values
- [ ] Verify Wandb logging receives correct API key
- [ ] Check that Python subprocess gets correct credentials
- [ ] Test with different `.env` values to ensure they're being read correctly

## Notes

- All environment variables use `env()` with sensible defaults where applicable
- Timeouts default to 900/60 seconds if not specified
- The `.env.example` file should be committed to version control
- The actual `.env` file should be in `.gitignore`
