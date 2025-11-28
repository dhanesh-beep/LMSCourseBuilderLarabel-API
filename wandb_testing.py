# wandb_logger.py
import wandb
import weave
import sys
import json
import os
import pandas as pd
from datetime import datetime

# ===================== Config =====================
DEFAULT_PROJECT_NAME = "Flowise_LLM_Course_Builder" 
DEFAULT_RUN_NAME = f"Flowise_Run_{datetime.now().strftime('%Y%m%d_%H%M%S')}"

# ===================== Main Function =====================
def main():
    try:
        # ------------------ Read JSON from Laravel ------------------
        data = json.load(sys.stdin)
        api_key = data.get("api_key", "")
        project_name = data.get("project_name", DEFAULT_PROJECT_NAME)
        run_name = data.get("run_name", DEFAULT_RUN_NAME)
        csv_path = data.get("csv_path", "Course_Builder_LLM_Analysis.csv")  

        # ------------------ Validate CSV ------------------
        if not os.path.exists(csv_path):
            print(f"Fine NOT Found ERROR: CSV file not found: {csv_path}", file=sys.stderr)
            sys.exit(1)

        # ------------------ Load CSV as DataFrame ------------------
        df = pd.read_csv(csv_path, delimiter=",")
        print(f"[INFO] Loaded CSV with {len(df)} rows and {len(df.columns)} columns")

        # ------------------ Init W&B ------------------
        wandb.login(key=api_key)
        run = wandb.init(
            project=project_name,
            name=run_name,
            config={
                "LLM_Model_Name": "claude-4-5-sonnet-latest",
                "Temp": 0.5,
                "Max_token": 64000,
                "Budget Tokens": 4048,
            },
        )

        # ------------------ Log scalar metrics from Laravel data ------------------
        wandb.log({
            "timestamp": data.get("timestamp"),
            "flowise_start": data.get("flowise_start"),
            "flowise_end": data.get("flowise_end"),
            "duration_sec": data.get("duration_sec"),
            "input_tokens": data.get("input_tokens"),
            "output_tokens": data.get("output_tokens"),
            "total_tokens": data.get("input_tokens", 0) + data.get("output_tokens", 0),
            "total_lessons": data.get("total_lessons"),
            "total_sections": data.get("total_sections"),
            "total_widgets": data.get("total_widgets"),
        })

        # ------------------ Init Weave ------------------
        weave.init(project_name)

        # Define Weave logging ops
        @weave.op()
        def log_csv_to_weave(row: dict):
            """Log a single CSV row to Weave"""
            return row

        # ------------------ Unified Logging ------------------
        def unified_logging(df: pd.DataFrame):
            numeric_cols = df.select_dtypes(include=["int64", "float64"]).columns
            string_cols = df.select_dtypes(include=["object"]).columns

            # 1️. Log string columns once as W&B table
            if len(string_cols) > 0:
                wandb_table = wandb.Table(dataframe=df[string_cols])
                wandb.log({"string_data": wandb_table})

            # 2. Iterate rows → log numeric trends & structured Weave logs
            for i, row in df.iterrows():
                row_dict = row.to_dict()

                # Log numeric columns step-by-step in W&B
                numeric_data = {col: row[col] for col in numeric_cols}
                wandb.log({**numeric_data, "step": i})

                # Log full row to Weave
                log_csv_to_weave(row_dict)

        unified_logging(df)

        # ------------------ Finalize ------------------
        run.finish()
        print(f"W&B + Weave logging completed successfully for: {run_name}")

    except Exception as e:
        print(f"Error in wandb_logger.py: {e}", file=sys.stderr)
        sys.exit(1)

# ===================== Entry Point =====================
if __name__ == "__main__":
    main()
    sys.exit(0)


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
