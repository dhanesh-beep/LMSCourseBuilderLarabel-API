# wandb_logger.py
import sys
import json
import weave
from datetime import datetime

# ===================== Config =====================
DEFAULT_PROJECT_NAME = "Flowise_LLM_Course_Builder_Eval_QUIZ"
DEFAULT_RUN_NAME = f"Flowise_Run_{datetime.now().strftime('%Y%m%d_%H%M%S')}"


# ===================== Field Mapping =====================
# Map Weave field → Input JSON field
TRACE_FIELD_MAP = {
    # Basic
    "timestamp": "timestamp",
    "requested_from": "requested_from",
    "pdf_file_name": "pdf_file_name",
    "Course_title": "Course_title",
    "pdf_pages_counts": "pdf_pages_counts",
    "image_counts": "Image_counts",
    "execution_time": "duration_sec",
    "input_tokens": "input_tokens",
    # "output_tokens": "output_tokens",
    # "total_tokens": "total_tokens",
    "total_lessons": "total_lessons",
    "total_sections": "total_sections",
    "total_widgets": "total_widgets",
    # Quiz Metrics
    # "quiz_count": "quiz_count",
    # "quiz_ids": "quiz_ids",
    # "quiz_question_counts": "quiz_question_counts",
    # "quiz_single_count": "quiz_single_count",
    # "quiz_multiple_count": "quiz_multiple_count",
    # "quiz_titles": "quiz_titles",
    # # Quiz Performance
    # "q_grounding_score": "q_grounding_score",
    # "q_accuracyScore": "q_accuracyScore",
    # "q_contextTokenOverlap": "q_contextTokenOverlap",
    # "q_response_length_balance": "q_response_length_balance",
    # "q_relevance_score": "q_relevance_score",
    # "q_evaluationSummary": "q_evaluationSummary",
    # Course Performance
    "grounding_score": "grounding_score",
    "completeness_score": "completeness_score",
    "response_length_balance": "response_length_balance",
    "context_token_overlap": "context_token_overlap",
    "overall_score": "overall_score",
    "justification": "justification",
}


# ===================== Helper =====================
def build_trace(data: dict) -> dict:
    """Builds trace dict using TRACE_FIELD_MAP"""
    trace = {k: data.get(v) for k, v in TRACE_FIELD_MAP.items()}

    # Standard Model Config (static)
    trace.update({
        "model_name": "claude-4-5-sonnet-latest",
        "temperature": 0.5,
        "max_tokens": 64000,
        "budget_tokens": 4048,
    })

    # Auto compute total tokens if not provided
    if trace.get("total_tokens") is None:
        trace["total_tokens"] = (trace.get("input_tokens") or 0) + (trace.get("output_tokens") or 0)

    return trace


# ===================== Main Function =====================
def main():
    try:
        # Read JSON from Laravel
        data = json.load(sys.stdin)

        # project_name = data.get("project_name", DEFAULT_PROJECT_NAME)
        # run_name = data.get("run_name", DEFAULT_RUN_NAME)
        project_name="Flowise_LLM_Course_Builder_Eval_QUIZ"
        run_name="Flowise_Run_20240606_123456"
        # Init Weave
        weave.init(project_name)

        @weave.op()
        def log_trace_to_weave(trace_data: dict):
            return trace_data

        # Build and log trace
        trace_data = build_trace(data)
        log_trace_to_weave(trace_data)

        print(f"W&B + Weave logging completed successfully for: {run_name}")

    except Exception as e:
        print(f"Error in wandb_logger.py: {e}", file=sys.stderr)
        sys.exit(1)


# ===================== Entry Point =====================
if __name__ == "__main__":
    main()
    sys.exit(0)

