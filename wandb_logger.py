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
    "pdf_pages_counts": "PDF_pages_Counts",
    "image_counts": "Image_counts",
    "execution_time": "duration_sec",
    "input_tokens": "input_tokens",
    "output_tokens": "output_tokens",
    "total_tokens": "total_tokens",
    "total_lessons": "total_lessons",
    "total_sections": "total_sections",
    "total_widgets": "total_widgets",
    # Quiz Metrics
    "quiz_count": "quiz_count",
    "quiz_ids": "quiz_ids",
    "quiz_question_counts": "quiz_question_counts",
    "quiz_single_count": "quiz_single_count",
    "quiz_multiple_count": "quiz_multiple_count",
    "quiz_titles": "quiz_titles",
    # Quiz Performance
    "q_grounding_score": "q_grounding_score",
    "q_accuracyScore": "q_accuracyScore",
    "q_contextTokenOverlap": "q_contextTokenOverlap",
    "q_response_length_balance": "q_response_length_balance",
    "q_relevance_score": "q_relevance_score",
    "q_evaluationSummary": "q_evaluationSummary",
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



# # wandb_logger.py
# import weave
# import sys
# import json
# import os
# # import pandas as pd
# from datetime import datetime

# # ===================== Config =====================
# DEFAULT_PROJECT_NAME = "Flowise_LLM_Course_Builder_Eval_QUIZ"
# #DEFAULT_RUN_NAME = f"Flowise_Run_{datetime.now().strftime('%Y%m%d_%H%M%S')}"

# # ===================== Main Function =====================
# def main():
#     try:
#         # ------------------ Read JSON from Laravel ------------------
#         data = json.load(sys.stdin)
#         api_key = data.get("api_key", "")
#         project_name = data.get("project_name", DEFAULT_PROJECT_NAME)
#         #run_name = data.get("run_name", DEFAULT_RUN_NAME)

#         # ------------------ Init Weave ------------------
#         weave.init(project_name)

#         # Define Weave logging ops
#         @weave.op()
#         def log_trace_to_weave(trace_data: dict):
#             """Log a single trace entry to Weave"""

#             return {
#                 "timestamp": trace_data.get("timestamp"),
#                 "requested_from": trace_data.get("requested_from"),
#                 "model_name": "claude-4-5-sonnet-latest",
#                 "temperature": 0.5,
#                 "max_tokens": 64000,
#                 "budget_tokens": 4048,
#                 "pdf_file_name": trace_data.get("pdf_file_name"),
#                 "Course_title": trace_data.get("Course_title"),
#                 "pdf_pages_counts": trace_data.get("PDF_pages_Counts"),
#                 "image_counts": trace_data.get("Image_counts"),
#                 "execution_time": trace_data.get("duration_sec"),
#                 "input_tokens": trace_data.get("input_tokens"),
#                 "output_tokens": trace_data.get("output_tokens"),
#                 "total_tokens": trace_data.get("input_tokens", 0) + trace_data.get("output_tokens", 0),
#                 "total_lessons": trace_data.get("total_lessons"),
#                 "total_sections": trace_data.get("total_sections"),
#                 "total_widgets": trace_data.get("total_widgets"),
#                  # QUIZ METRICS
#                 "quiz_count": trace_data.get("quiz_count"),
#                 "quiz_ids": trace_data.get("quiz_ids"),
#                 "quiz_question_counts": trace_data.get("quiz_question_counts"),
#                 "quiz_single_count": trace_data.get("quiz_single_count"),
#                 "quiz_multiple_count": trace_data.get("quiz_multiple_count"),
#                 "quiz_titles": trace_data.get("quiz_titles"),
#                  #Quiz Performance
#                 "q_grounding_score": trace_data.get("q_grounding_score"),
#                 "q_accuracyScore": trace_data.get("q_accuracyScore"),
#                 "q_contextTokenOverlap": trace_data.get("q_contextTokenOverlap"),
#                 "q_response_length_balance": trace_data.get("q_response_length_balance"),
#                 "q_relevance_score": trace_data.get("q_relevance_score"),
#                 "q_evaluationSummary": trace_data.get("q_evaluationSummary"),
#                  #Course Performance
#                 "grounding_score": trace_data.get("grounding_score"),
#                 "completeness_score": trace_data.get("completeness_score"),
#                 "response_length_balance": trace_data.get("response_length_balance"),
#                 "context_token_overlap": trace_data.get("context_token_overlap"),
#                 "overall_score": trace_data.get("overall_score"),
#                 "justification": trace_data.get("justification"),
#                 }

#         # ------------------ Log Current Trace ------------------
#         trace_data = {
#             "timestamp": data.get("timestamp"),
#             "requested_from": data.get("requested_from"),
#             "pdf_file_name": data.get("pdf_file_name"),
#             "Course_title": data.get("Course_title"),
#             "PDF_pages_Counts": data.get("PDF_pages_Counts"),
#             "Image_counts": data.get("Image_counts"),
#             "duration_sec": data.get("duration_sec"),
#             "input_tokens": data.get("input_tokens"),
#             "output_tokens": data.get("output_tokens"),
#             "total_tokens": data.get("total_tokens"),
#             "total_lessons": data.get("total_lessons"),
#             "total_sections": data.get("total_sections"),
#             "total_widgets": data.get("total_widgets"),
#             # QUIZ METRICS
#             "quiz_count": data.get("quiz_count"),
#             "quiz_ids": data.get("quiz_ids"),
#             "quiz_question_counts": data.get("quiz_question_counts"),
#             "quiz_single_count": data.get("quiz_single_count"),
#             "quiz_multiple_count": data.get("quiz_multiple_count"),
#             "quiz_titles": data.get("quiz_titles"),
#             #Quiz Performance
#             "q_grounding_score": data.get("q_grounding_score"),
#             "q_accuracyScore": data.get("q_accuracyScore"),
#             "q_contextTokenOverlap": data.get("q_contextTokenOverlap"),
#             "q_response_length_balance": data.get("q_response_length_balance"),
#             "q_relevance_score": data.get("q_relevance_score"),
#             "q_evaluationSummary": data.get("q_evaluationSummary"),
#              #Course Performance
#             "grounding_score": data.get("grounding_score"),
#             "completeness_score": data.get("completeness_score"),
#             "response_length_balance": data.get("response_length_balance"),
#             "context_token_overlap": data.get("context_token_overlap"),
#             "overall_score": data.get("overall_score"),
#             "justification": data.get("justification"),
#         }

#         # Log the current trace to Weave
#         log_trace_to_weave(trace_data)

#         # ------------------ Finalize ------------------
#         ##run.finish()
#         print(f"W&B + Weave logging completed successfully for the Project : {DEFAULT_PROJECT_NAME}")

#     except Exception as e:
#         print(f"Error in wandb_logger.py: {e}", file=sys.stderr)
#         sys.exit(1)

# # ===================== Entry Point =====================
# if __name__ == "__main__":
#     main()
#     sys.exit(0)

# data={"timestamp":"2025-12-03T16:34:56Z","requested_from":"Backend_API","pdf_file_name":"it-acceptable-use-policy_short.pdf","Course_title":"IT Acceptable Use Policy Training Course","PDF_pages_Counts":3,"Image_counts":2,
#     "model_name":"claude-4-5-sonnet-latest",
#     "execution_time":120,"project_name":"Flowise_LLM_Course_Builder_Eval_QUIZ","run_name":"Flowise_Run_20240606_123456",
#     "duration_sec":120, "input_tokens":1418,"output_tokens":12884,"total_tokens":14242,"total_lessons":5,
#     "total_sections":30,"total_widgets":45,"quiz_count":5, "quiz_ids":4|5|6|7|8,
#     "quiz_question_counts":5|5|8|5|15,"quiz_single_count":25,
#     "quiz_multiple_count":13,"quiz_titles":"Why we have this Policy | Who this Policy relates to | Other Policies, Procedures and Guidelines | Overview of this Policy | Service Access and Password/Account Management",
#     "q_grounding_score":0.98,"q_accuracyScore":0.92,"q_contextTokenOverlap":0.94,
#         "q_response_length_balance":0.92,"q_relevance_score":0.95,
#         "q_evaluationSummary":"The quizzes were well-grounded and accurate.",
#     "grounding_score":0.85,"completeness_score":0.9,"response_length_balance":0.8,"context_token_overlap":0.75,"overall_score":0.83,"justification":"The course content demonstrates exceptional grounding in the source policy document, with all lessons directly derived from the five main sections of the IT Acceptable Use Policy. Completeness is excellent—each of the five lessons contains multiple comprehensive sections with detailed explanations, practical examples, learning tips, and reflection questions with answers. Response length balance is well-maintained, with each section providing sufficient detail (30-50 words for descriptions, 60-100 words for content widgets) without becoming overly verbose. Context token overlap is very high, as the course consistently uses key terminology and concepts from the source document (e.g., 'staff,' 'IET systems,' 'login credentials,' 'IT Service Centre,' 'Password Guidance,' 'UK GDPR,' 'Computer Misuse Act') while transforming policy language into learner-friendly instructional content. The structure follows the exact lesson titles provided, includes quiz widgets at the end of each lesson with correct quiz_id numbering starting from 6, and maintains proper JSON hierarchy with each widget in its own row/column structure. All prohibited activities, password requirements, and access management procedures from the source document are accurately represented and explained with practical context for learners."
#     }