# wandb_logger.py
import wandb
import weave
import sys
import json
import os
#import pandas as pd
from datetime import datetime

# ===================== Config =====================
DEFAULT_PROJECT_NAME = "Flowise_LLM_Course_Builder_Eval_QUIZ" 
DEFAULT_RUN_NAME = f"Flowise_Run_{datetime.now().strftime('%Y%m%d_%H%M%S')}"

# data={"api_key":"9a2dd71fea975e82e9f4efcf5cabe5ded3b52326","model_name":"claude-4-5-sonnet-latest",
#       "project_name":"Flowise_LLM_Course_Builder_Eval_QUIZ","run_name":"Flowise_Run_20240606_123456",
#       "timestamp":"2024-06-06T12:34:56Z","duration_sec":120,
#       "input_tokens":1500,"output_tokens":3500,"total_lessons":10,
#       "total_sections":30,"total_widgets":15,"quiz_count":5,
#       "quiz_ids":["quiz1","quiz2","quiz3","quiz4","quiz5"],
#       "quiz_question_counts":[10,12,8,15,9],"quiz_single_count":30,
#       "quiz_multiple_count":24,"grounding_score":0.85,"completeness_score":0.9,"response_length_balance":0.8,
#       "context_token_overlap":0.75,"overall_score":0.83,"justification":"The model performed well in generating quizzes with relevant content."}


# ===================== Main Function =====================
def main():
    try:
        # ------------------ Read JSON from Laravel ------------------
        data = json.load(sys.stdin)
        api_key = data.get("api_key", "")
        project_name = data.get("project_name", DEFAULT_PROJECT_NAME)
        run_name = data.get("run_name", DEFAULT_RUN_NAME)

        # wandb.login(key=data.get("api_key"))

        # ------------------ Init Weave ------------------
        weave.init(project_name)

        # Define Weave logging ops
        @weave.op()
        def log_trace_to_weave(trace_data: dict):
            """Log a single trace entry to Weave"""
        
            return {
                "timestamp": trace_data.get("timestamp"),
                "model_name": "claude-4-5-sonnet-latest",
                "temperature": 0.5,
                "max_tokens": 64000,
                "execution_time": trace_data.get("duration_sec"),
                "input_tokens": trace_data.get("input_tokens"),
                "output_tokens": trace_data.get("output_tokens"),
                "total_tokens": trace_data.get("input_tokens", 0) + trace_data.get("output_tokens", 0),
                "total_lessons": trace_data.get("total_lessons"),
                "total_sections": trace_data.get("total_sections"),
                "total_widgets": trace_data.get("total_widgets"),
                 # QUIZ METRICS
                "quiz_count": trace_data.get("quiz_count"),
                "quiz_ids": trace_data.get("quiz_ids"),
                "quiz_question_counts": trace_data.get("quiz_question_counts"),
                "quiz_single_count": trace_data.get("quiz_single_count"),
                "quiz_multiple_count": trace_data.get("quiz_multiple_count"),
                 #Course Performance   
                "grounding_score": trace_data.get("grounding_score"),
                "completeness_score": trace_data.get("completeness_score"),
                "response_length_balance": trace_data.get("response_length_balance"),
                "context_token_overlap": trace_data.get("context_token_overlap"),
                "overall_score": trace_data.get("overall_score"),
                "justification": trace_data.get("justification"),
                }

        # ------------------ Log Current Trace ------------------
        trace_data = {
            "timestamp": data.get("timestamp"),
            "duration_sec": data.get("duration_sec"),
            "input_tokens": data.get("input_tokens"),
            "output_tokens": data.get("output_tokens"),
            "total_lessons": data.get("total_lessons"),
            "total_sections": data.get("total_sections"),
            "total_widgets": data.get("total_widgets"),            
            # QUIZ METRICS
            "quiz_count": data.get("quiz_count"),
            "quiz_ids": data.get("quiz_ids"),
            "quiz_question_counts": data.get("quiz_question_counts"),
            "quiz_single_count": data.get("quiz_single_count"),
            "quiz_multiple_count": data.get("quiz_multiple_count"),
             #Course Performance
            "grounding_score": data.get("grounding_score"),
            "completeness_score": data.get("completeness_score"),
            "response_length_balance": data.get("response_length_balance"),
            "context_token_overlap": data.get("context_token_overlap"),
            "overall_score": data.get("overall_score"),
            "justification": data.get("justification"),
        }

        # Log the current trace to Weave
        log_trace_to_weave(trace_data)

        # ------------------ Finalize ------------------
        ##run.finish()
        print(f"W&B + Weave logging completed successfully for: {run_name}")

    except Exception as e:
        print(f"Error in wandb_logger.py: {e}", file=sys.stderr)
        sys.exit(1)

# ===================== Entry Point =====================
if __name__ == "__main__":
    main()
    sys.exit(0)

