
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
                "requested_from": trace_data.get("requested_from"),
                "model_name": "claude-4-5-sonnet-latest",
                "temperature": 0.5,
                "max_tokens": 64000,
                "budget_tokens": 4048,
                "pdf_file_name": trace_data.get("pdf_file_name"),
                "Course_title": trace_data.get("Course_title"),
                "pdf_pages_counts": trace_data.get("PDF_pages_Counts"),
                "image_counts": trace_data.get("Image_counts"),
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
                "quiz_titles": trace_data.get("quiz_titles"),
                 #Quiz Performance   
                "q_grounding_score": trace_data.get("q_grounding_score"),
                "q_accuracyScore": trace_data.get("q_accuracyScore"),
                "q_contextTokenOverlap": trace_data.get("q_contextTokenOverlap"),
                "q_response_length_balance": trace_data.get("q_response_length_balance"),
                "q_relevance_score": trace_data.get("q_relevance_score"),
                "q_evaluationSummary": trace_data.get("q_evaluationSummary"),

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
            "requested_from": data.get("requested_from"),
            "pdf_file_name": data.get("pdf_file_name"),
            "Course_title": data.get("Course_title"),
            "PDF_pages_Counts": data.get("PDF_pages_Counts"),
            "Image_counts": data.get("Image_counts"),
            "duration_sec": data.get("duration_sec"),
            "input_tokens": data.get("input_tokens"),
            "output_tokens": data.get("output_tokens"),
            "total_tokens": data.get("total_tokens"),
            "total_lessons": data.get("total_lessons"),
            "total_sections": data.get("total_sections"),
            "total_widgets": data.get("total_widgets"),            
            # QUIZ METRICS
            "quiz_count": data.get("quiz_count"),
            "quiz_ids": data.get("quiz_ids"),
            "quiz_question_counts": data.get("quiz_question_counts"),
            "quiz_single_count": data.get("quiz_single_count"),
            "quiz_multiple_count": data.get("quiz_multiple_count"),
            "quiz_titles": data.get("quiz_titles"),
            #Quiz Performance
            "q_grounding_score": data.get("q_grounding_score"),
            "q_accuracyScore": data.get("q_accuracyScore"),
            "q_contextTokenOverlap": data.get("q_contextTokenOverlap"),
            "q_response_length_balance": data.get("q_response_length_balance"),
            "q_relevance_score": data.get("q_relevance_score"),
            "q_evaluationSummary": data.get("q_evaluationSummary"),
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


# # wandb_logger.py
# import wandb
# import weave
# import sys
# import json
# import os
# import pandas as pd
# from datetime import datetime

# # ===================== Config =====================
# DEFAULT_PROJECT_NAME = "Flowise_LLM_Course_Builder" 
# DEFAULT_RUN_NAME = f"Flowise_Run_{datetime.now().strftime('%Y%m%d_%H%M%S')}"

# # ===================== Main Function =====================
# def main():
#     try:
#         # ------------------ Read JSON from Laravel ------------------
#         data = json.load(sys.stdin)
#         api_key = data.get("api_key", "")
#         project_name = data.get("project_name", DEFAULT_PROJECT_NAME)
#         run_name = data.get("run_name", DEFAULT_RUN_NAME)
#         csv_path = data.get("csv_path", "Course_Builder_LLM_Analysis.csv")  

#         # ------------------ Validate CSV ------------------
#         if not os.path.exists(csv_path):
#             print(f"Fine NOT Found ERROR: CSV file not found: {csv_path}", file=sys.stderr)
#             sys.exit(1)

#         # ------------------ Load CSV as DataFrame ------------------
#         df = pd.read_csv(csv_path, delimiter=",")
#         print(f"[INFO] Loaded CSV with {len(df)} rows and {len(df.columns)} columns")

#         # ------------------ Init W&B ------------------
#         wandb.login(key=api_key)
#         run = wandb.init(
#             project=project_name,
#             name=run_name,
#             config={
#                 "LLM_Model_Name": "claude-4-5-sonnet-latest",
#                 "Temp": 0.5,
#                 "Max_token": 64000,
#                 "Budget Tokens": 4048,
#             },
#         )

#         # ------------------ Log scalar metrics from Laravel data ------------------
#         wandb.log({
#             "timestamp": data.get("timestamp"),
#             "flowise_start": data.get("flowise_start"),
#             "flowise_end": data.get("flowise_end"),
#             "duration_sec": data.get("duration_sec"),
#             "input_tokens": data.get("input_tokens"),
#             "output_tokens": data.get("output_tokens"),
#             "total_tokens": data.get("input_tokens", 0) + data.get("output_tokens", 0),
#             "total_lessons": data.get("total_lessons"),
#             "total_sections": data.get("total_sections"),
#             "total_widgets": data.get("total_widgets"),
#         })

#         # ------------------ Init Weave ------------------
#         weave.init(project_name)

#         # Define Weave logging ops
#         @weave.op()
#         def log_csv_to_weave(row: dict):
#             """Log a single CSV row to Weave"""
#             return row

#         # ------------------ Unified Logging ------------------
#         def unified_logging(df: pd.DataFrame):
#             numeric_cols = df.select_dtypes(include=["int64", "float64"]).columns
#             string_cols = df.select_dtypes(include=["object"]).columns

#             # 1️. Log string columns once as W&B table
#             if len(string_cols) > 0:
#                 wandb_table = wandb.Table(dataframe=df[string_cols])
#                 wandb.log({"string_data": wandb_table})

#             # 2. Iterate rows → log numeric trends & structured Weave logs
#             for i, row in df.iterrows():
#                 row_dict = row.to_dict()

#                 # Log numeric columns step-by-step in W&B
#                 numeric_data = {col: row[col] for col in numeric_cols}
#                 wandb.log({**numeric_data, "step": i})

#                 # Log full row to Weave
#                 log_csv_to_weave(row_dict)

#         unified_logging(df)

#         # ------------------ Finalize ------------------
#         run.finish()
#         print(f"W&B + Weave logging completed successfully for: {run_name}")

#     except Exception as e:
#         print(f"Error in wandb_logger.py: {e}", file=sys.stderr)
#         sys.exit(1)

# # ===================== Entry Point =====================
# if __name__ == "__main__":
#     main()
#     sys.exit(0)


# # wandb_logger.py
# from fastapi import params
# import wandb
# import weave
# import sys
# from datetime import datetime
# import json
# import os
# import pandas as pd

# API_KEY = "9a2dd71fea975e82e9f4efcf5cabe5ded3b52326"
# PROJECT_NAME = "Flowise_LLM_Course_Builder"

# df=pd.read_csv("Course_Builder_LLM_Analysis.csv",delimiter=";")
# print(df)

# def main():
#     try:
#         # Read JSON from Laravel stdin
#         data = json.load(sys.stdin)

#         # --- Extract info ---
#         api_key = data.get("api_key")
#         project_name = data.get("project_name", "Flowise_LLM_Course_Builder")
#         run_name = data.get("run_name", "Flowise_v0_run")
#         csv_path = data.get("csv_path", "Course_Builder_LLM_Analysis.csv")

#         # --- Authenticate & Init Run ---
#         wandb.login(key=api_key)

#         run = wandb.init(
#             project=project_name,
#             name=run_name,
#             config={
#                 "LLM_Model_Name": "claude-3-7-sonnet-latest",
#                 "Temp": 0.5,
#                 "Max_token": 15000,
#                 "Budget Tokens":4048
#             }
#         )

#         # --- Log metrics ---
#         wandb.log({
#             "timestamp": data.get("timestamp"),
#             "flowise_start": data.get("flowise_start"),
#             "flowise_end": data.get("flowise_end"),
#             "duration_sec": data.get("duration_sec"),
#             "input_tokens": data.get("input_tokens"),
#             "output_tokens": data.get("output_tokens"),
#             "total_tokens": data.get("input_tokens", 0) + data.get("output_tokens", 0),
#             "total_lessons": data.get("total_lessons"),
#             "total_sections": data.get("total_sections"),
#             "total_widgets": data.get("total_widgets"),
#         })

#         # =============== 📊 WEAVE Table Logging ===============
#         weave.init(project_name)  # same project as W&B
#         table_name = "Flowise_LLM_Runs"  

#         # ================== Define Weave Ops ==================
#         @weave.op()
#         def log_csv_row(csv_row: dict) -> dict:
#             """
#             Log one pipeline execution (single query) into Weave.
#             """
#             return csv_row
        
#         @weave.op()
#         def pipeline_run(row_dict: dict) -> dict:
#             return log_csv_row(row_dict)

#         def unified_logging(df: pd.DataFrame):
#             numeric_cols = df.select_dtypes(include=["int64", "float64"]).columns
#             string_cols = df.select_dtypes(include=["object"]).columns

#             # 1️⃣ Log string cols once as a W&B table
#             wandb_table = wandb.Table(dataframe=df[string_cols])
#             wandb.log({"string_data": wandb_table})

#             # 2️⃣ Iterate rows → log to W&B + Weave
#             for i, row in df.iterrows():
#                 row_dict = row.to_dict()

#                 # W&B: log numeric trends step by step
#                 for col in numeric_cols:
#                     wandb.log({col: row[col], "step": i})

#                 # Weave: structured log
#                 pipeline_run(row_dict)

#         print("✅ W&B + Table logging completed for run:", run_name)

#     except Exception as e:
#         print(f"❌ Error in wandb_logger.py: {e}", file=sys.stderr)
#         sys.exit(1)

# if __name__ == "__main__":
#     main()
