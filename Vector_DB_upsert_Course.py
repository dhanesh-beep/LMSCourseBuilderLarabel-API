
# ============================================
# 1. Initialize Pinecone
# ============================================
import uuid
from pinecone import Pinecone, ServerlessSpec
import time
# from sentence_transformers import SentenceTransformer
import ollama
from dynamic_text_loader import load_course_details

#----------------- Configuration----------------
PINECONE_API_KEY = "PINECONE_API_KEY"
# INDEX_NAME = "lms-course-chatbot-knowledgebase"
INDEX_NAME = "lms-course-knowledgebase-v1"

DIMENSION = 1024     #384   
METRIC = "cosine"

CHUNK_SIZE = 1000
CHUNK_OVERLAP = 200
# Initialize Pinecone client

try:
    pc = Pinecone(api_key=PINECONE_API_KEY)

    # -------- CHECK / CREATE INDEX ---------
    existing_indexes = [idx["name"] for idx in pc.list_indexes()]

    if INDEX_NAME not in existing_indexes:
        print(f"⚠ Index '{INDEX_NAME}' not found. Creating...")

        pc.create_index(
            name=INDEX_NAME,
            dimension=DIMENSION,
            metric=METRIC,
            spec=ServerlessSpec(
                cloud="aws",
                region="us-east-1"
            )
        )

        # Wait for index to be ready
        while not pc.describe_index(INDEX_NAME).status["ready"]:
            time.sleep(1)

        print(f"✅ Index '{INDEX_NAME}' created successfully")
    else:
        # ------------- CONNECT INDEX -----------
        print(f"✅ Index '{INDEX_NAME}' already exists")

    index = pc.Index(INDEX_NAME)
    print(f"✅ Pinecone connected to Index: {INDEX_NAME} successfully!")

except Exception as e:
    print(f"❌ Failed to connect to Pinecone: {e}")
    exit(1)

EMBED_MODEL = "mxbai-embed-large"

def embed(text: str) -> list[float]:
    response = ollama.embeddings(
        model=EMBED_MODEL,
        prompt=text
    )
    return response["embedding"]

# -------- TEXT CHUNKING -----------------
def chunk_text(text, chunk_size=1000, overlap=200):
    chunks = []
    start = 0

    while start < len(text):
        end = start + chunk_size
        chunks.append(text[start:end])
        start = end - overlap

    return chunks

text=load_course_details()

# # -------- CHUNK TEXT ---------------------
chunks = chunk_text(text, CHUNK_SIZE, CHUNK_OVERLAP)
print(f"📄 Total chunks: {len(chunks)}")

# -------- PREPARE VECTORS --------------
vectors = []

course_id = '1'
course_slug = "it-acceptable-use-policy-training"
doc_version = "v1"  # increment on update

for i, chunk in enumerate(chunks):
    embedding = embed(chunk)

    vectors.append({
        "id": f"{course_id}_{course_slug}_{doc_version}_chunk_{i}",
        "values": embedding,
        "metadata": {
            "course_id": course_id,
            "course_slug": course_slug,
            "doc_version": doc_version,
            "chunk_index": i,
            "content_type": "course_text",
            # "text": chunk[:100]  # optional (trim if long)
            "text": chunk 
        }
    })
# -------- UPSERT -----------------------
index.upsert(vectors=vectors)

# print("🚀 Data upserted successfully into Pinecone")

# #-------------------------------------------Quick Retrieval Test (Optional)--------------------------------
# query = "what is title of the course?"
# query_vector = embed(query)

# res = index.query(
#     vector=query_vector,
#     top_k=3,
#     include_metadata=True
# )

# for match in res["matches"]:
#     print(match["score"])
#     print(match["metadata"]["text"])
