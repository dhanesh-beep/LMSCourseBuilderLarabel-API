
# Vector Database Index Calling Script
# This script demonstrates how to query Pinecone vector database for LMS chatbot knowledge base

# ============================================
# 1. Initialize Pinecone
# ============================================
from pinecone import Pinecone
from sentence_transformers import SentenceTransformer

# Configuration
PINECONE_API_KEY = "pcsk_4y6VRK_77QymMYerdAjm3AeS7sBt2798VV15YDvBzCD1PCsTZMrq7BQa9V45f4ovxkqrQ7"
INDEX_NAME = "lmschatbot-knowledgebase"

# Initialize Pinecone client
try:
    pc = Pinecone(api_key=PINECONE_API_KEY)
    index = pc.Index(INDEX_NAME)
    print("✅ Pinecone connected successfully!")
except Exception as e:
    print(f"❌ Failed to connect to Pinecone: {e}")
    exit(1)

# ============================================
# 2. Load Open-Source Embedding Model
# ============================================
try:
    # Open-source embedding model (768 dimensions to match Pinecone index)
    embedding_model = SentenceTransformer("all-mpnet-base-v2")  # 768-dimensional embeddings
    print("✅ Embedding model loaded successfully!")
except Exception as e:
    print(f"❌ Failed to load embedding model: {e}")
    exit(1)

def get_embedding(text: str):
    """Generate embeddings for input text"""
    try:
        embedding = embedding_model.encode(text, normalize_embeddings=True)
        return embedding.tolist()
    except Exception as e:
        print(f"❌ Failed to generate embedding: {e}")
        return None

# ============================================
# 3. Query Pinecone Using Text Input
# ============================================
def query_pinecone(query_text: str, top_k: int = 4, course_id: str = None, namespace: str = "lmschatbot"):
    """Query Pinecone vector database with text input"""

    # Generate embedding for query
    query_vector = get_embedding(query_text)
    if query_vector is None:
        return None

    # Prepare query parameters
    query_params = {
        "vector": query_vector,
        "top_k": top_k,
        "include_metadata": True,
        "namespace": namespace
    }

    # Add filter if course_id is specified
    if course_id:
        query_params["filter"] = {"course_id": course_id}

    try:
        results = index.query(**query_params)
        return results
    except Exception as e:
        print(f"❌ Failed to query Pinecone: {e}")
        return None

# ============================================
# 4. Extract Relevant Course Content
# ============================================
def extract_context(results):
    """Extract and format retrieved context from Pinecone results"""
    if not results or not hasattr(results, 'matches'):
        return []

    retrieved_context = []
    for match in results.matches:
        retrieved_context.append({
            "score": match["score"],
            "text": match["metadata"].get("text", ""),
            "course_id": match["metadata"].get("course_id", ""),
            "lesson_id": match["metadata"].get("lesson_id", "")
        })

    return retrieved_context

# ============================================
# 5. Full Retrieval Function (Reusable)
# ============================================
def retrieve_course_context(query: str, course_id: str = None, top_k: int = 5):
    """Complete function to retrieve course context from vector database"""

    # Query Pinecone
    results = query_pinecone(query, top_k=top_k, course_id=course_id)
    if results is None:
        return ""

    # Extract context
    context_data = extract_context(results)

    # Merge context for LLM
    context_text = "\n\n".join(item["text"] for item in context_data)

    return context_text

# ============================================
# 6. Test the Implementation
# ============================================
if __name__ == "__main__":
    # Test query
    test_query = "Explain Python with example"

    print(f"🔍 Querying: '{test_query}'")
    print("-" * 50)

    # Get results
    results = query_pinecone(test_query, top_k=4)
    if results:
        context_data = extract_context(results)

        print(f"📊 Found {len(context_data)} relevant matches:\n")

        for i, item in enumerate(context_data, 1):
            print(f"{i}. Score: {item['score']:.4f}")
            print(f"   Text: {item['text'][:200]}...")
            if item['course_id']:
                print(f"   Course: {item['course_id']}")
            print()

        # Show merged context
        merged_context = "\n\n".join(item["text"] for item in context_data)
        print("📝 Merged Context for LLM:")
        print("=" * 50)
        print(merged_context[:500] + "..." if len(merged_context) > 500 else merged_context)

    # Test with course filter
    print("\n" + "="*50)
    # print("🔍 Testing with course filter (if available):")
    # filtered_results = query_pinecone(test_query, top_k=2, course_id="AI-101")
    # if filtered_results and filtered_results["matches"]:
    #     print(f"✅ Found {len(filtered_results['matches'])} matches for course AI-101")
    # else:
    #     print("ℹ️  No results found for course AI-101 (course may not exist in database)")

    print("\n✅ Vector database querying test completed!")


