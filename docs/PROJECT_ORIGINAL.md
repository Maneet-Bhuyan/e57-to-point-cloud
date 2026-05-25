# Point Cloud to 3D Mesh Converter (E57 → OBJ)

## 1. Solution Approach

This project converts 3D scan data from `.e57` files into high-quality triangle meshes. The process is designed to handle real-world scan data and produce clean, color-preserved 3D models.

**Step-by-step logic:**

- **Data Extraction**  
  We begin by reading all available scans from the `.e57` file using the PyE57 library. For each scan, we extract:
  - 3D coordinates (X, Y, Z)
  - Color information (Red, Green, Blue)
  - Intensity (if color is unavailable)  
  This ensures the point cloud is both geometrically and visually accurate.

- **Point Cloud Visualization**  
  Using Open3D, the point cloud is rendered in 3D space. Normals are estimated and oriented, which are necessary for mesh generation. This step verifies the quality of the scan and allows for manual inspection.

- **Mesh Generation (Ball Pivoting Algorithm)**  
  The BPA algorithm simulates a ball rolling over the cloud of points to create triangle meshes. The ball radius is automatically calculated based on the point spacing to best fit the surface. This allows the method to adapt to different geometries within the object.

- **Mesh Export**  
  The generated mesh includes vertex colors and normals and is saved in the `.obj` format. This format is widely used in modeling, simulation, and 3D printing, making the result highly reusable.

---

## 2. Implementation Details

- **Language**: Python 3  
- **Libraries/Technologies**:
  - Open3D: Point cloud and mesh processing
  - PyE57: Reading `.e57` 3D scan files
  - NumPy: Numerical operations
  - Matplotlib: For color mapping (when intensity data is used)
  - TQDM: Progress bars for scan loading

---

## 3. Execution Steps

1. Place your `.e57` scan file (e.g., `pump.e57`) in the project directory.
2. Open the `main.py` file.
3. Inside the `Config` class, set:
   ```python
   INPUT_FILE = "pump.e57"
   OUTPUT_DIR = "output_directory"
   ```
4. Open a terminal or command prompt.
5. Run the script using:
   ```bash
   python main.py
   ```

---

## 4. Dependencies

Install the following libraries before running the code:

```bash
pip install open3d
pip install pye57
pip install numpy
pip install matplotlib
pip install tqdm
```

---

## 5. Expected Output

- A point cloud visualization window will open showing the scanned object.
- ![image](https://github.com/user-attachments/assets/feb5690b-cccb-43b4-8ab6-676e5c9a617b)
-![Screenshot 2025-04-04 103458](https://github.com/user-attachments/assets/233a0622-c45d-422e-8c48-27db354895c7)
- A 3D triangle mesh is generated using the BPA algorithm.
- The 3d mesh output is shown in a window.  
- The mesh is saved as: 

  ```
  output_directory/bpa_mesh.obj
  ```

- The mesh will include color and normal data.
