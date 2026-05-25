import numpy as np
import open3d as o3d
import pye57
import os
from tqdm import tqdm
from typing import Optional
import time
import multiprocessing
import matplotlib.pyplot as plt

# ========================
# ENHANCED CONFIGURATION
# ========================
class Config:
    # BPA Configuration (will be auto-calculated)
    BPA_RADII = None  # Will be set dynamically
    BPA_CLUSTER_TOL = 0.02
    
    # Visualization
    POINT_SIZE = 2.5
    BACKGROUND_COLOR = [0.05, 0.05, 0.1]
    HIGHLIGHT_COLOR = [0.9, 0.2, 0.2]
    
    # Processing
    INTENSITY_SCALING = 255.0
    INPUT_FILE = r"C:\Godown\Shubh-P\pump.e57"
    OUTPUT_DIR = r"C:\Godown\Shubh-P\Shubh-P"
    MESH_OUTPUT_FILE = os.path.join(OUTPUT_DIR, "pump_bpa_mesh.obj")
    
    @staticmethod
    def validate_paths():
        if not os.path.exists(Config.INPUT_FILE):
            raise FileNotFoundError(f"Input file not found: {Config.INPUT_FILE}")
        os.makedirs(Config.OUTPUT_DIR, exist_ok=True)

# ========================
# AUTO-RADIUS CALCULATION
# ========================
def calculate_adaptive_radii(pcd: o3d.geometry.PointCloud, base_multiplier=4.0):
    """Automatically computes optimal BPA radii based on point density"""
    distances = pcd.compute_nearest_neighbor_distance()
    avg_dist = np.mean(distances)
    min_radius = base_multiplier * avg_dist
    max_radius = min_radius * 8  # For large flat areas
    
    # Logarithmic spacing for better geometric coverage
    radii = np.exp(np.linspace(np.log(min_radius), np.log(max_radius), 4))
    return radii.tolist()

# ========================
# ENHANCED E57 LOADING
# ========================
def load_e57(file_path: str) -> Optional[o3d.geometry.PointCloud]:
    print("\n[1/4] Loading E57 file with enhanced color handling...")
    start_time = time.time()
    
    try:
        e57 = pye57.E57(file_path)
    except Exception as e:
        print(f"Failed to read E57 file: {str(e)}")
        return None

    points, colors = [], []
    for i in tqdm(range(e57.scan_count), desc="Processing scans"):
        try:
            data = e57.read_scan(i, colors=True, intensity=True)
            xyz = np.vstack((data["cartesianX"], data["cartesianY"], data["cartesianZ"])).T

            if "colorRed" in data:
                color = np.vstack((
                    data["colorRed"] / 255.0,
                    data["colorGreen"] / 255.0,
                    data["colorBlue"] / 255.0
                )).T
                color = np.power(color, 1/2.2)  # Gamma correction
            else:
                intensity = np.clip(data["intensity"] / Config.INTENSITY_SCALING, 0, 1)
                cmap = plt.get_cmap("viridis")
                color = cmap(intensity)[:, :3]

            points.append(xyz)
            colors.append(color)
        except Exception as e:
            print(f"Error processing scan {i}: {str(e)}")
            continue

    if not points:
        print("No valid point data found in E57 file")
        return None

    combined_points = np.vstack(points)
    combined_colors = np.vstack(colors)

    valid_mask = np.isfinite(combined_points).all(axis=1)
    combined_points = combined_points[valid_mask]
    combined_colors = combined_colors[valid_mask]

    pcd = o3d.geometry.PointCloud()
    pcd.points = o3d.utility.Vector3dVector(combined_points)
    pcd.colors = o3d.utility.Vector3dVector(combined_colors)

    # Downsample and clean the point cloud
    pcd = pcd.voxel_down_sample(voxel_size=0.005)
    pcd, _ = pcd.remove_statistical_outlier(nb_neighbors=50, std_ratio=2.0)

    print(f"[INFO] Loaded {len(pcd.points)} points in {time.time() - start_time:.2f}s")
    return pcd

# ========================
# ADVANCED VISUALIZATION
# ========================
def visualize_point_cloud(pcd: o3d.geometry.PointCloud):
    print("\n[2/4] Advanced point cloud visualization...")
    
    pcd.estimate_normals(
        search_param=o3d.geometry.KDTreeSearchParamHybrid(
            radius=0.1,
            max_nn=100
        )
    )
    pcd.orient_normals_towards_camera_location()
    
    vis = o3d.visualization.Visualizer()
    vis.create_window("Pump Point Cloud", width=1600, height=900)
    vis.add_geometry(o3d.geometry.TriangleMesh.create_coordinate_frame(size=0.3))
    
    opt = vis.get_render_option()
    opt.background_color = np.array(Config.BACKGROUND_COLOR)
    opt.point_size = Config.POINT_SIZE
    opt.light_on = True
    opt.show_coordinate_frame = True
    
    colors = np.asarray(pcd.colors)
    if np.max(colors) > 1.0:
        pcd.colors = o3d.utility.Vector3dVector(colors / 255.0)
    
    vis.add_geometry(pcd)
    ctr = vis.get_view_control()
    ctr.set_front([-0.5, -0.3, -0.8])
    ctr.set_up([0.1, -0.9, 0.4])
    ctr.set_zoom(0.7)
    vis.run()
    vis.destroy_window()

# ========================
# UPDATED BPA MESH GENERATION
# ========================
def generate_bpa_mesh(pcd: o3d.geometry.PointCloud):
    print("\n[3/4] Generating BPA mesh with adaptive radii...")
    start_time = time.time()
    
    Config.BPA_RADII = calculate_adaptive_radii(pcd)
    print(f"[INFO] Auto-calculated BPA radii: {Config.BPA_RADII}")
    
    pcd.estimate_normals(
        search_param=o3d.geometry.KDTreeSearchParamHybrid(
            radius=0.15,
            max_nn=100
        )
    )
    pcd.orient_normals_consistent_tangent_plane(30)
    
    mesh = o3d.geometry.TriangleMesh.create_from_point_cloud_ball_pivoting(
        pcd,
        o3d.utility.DoubleVector(Config.BPA_RADII)
    )
    
    mesh = mesh.remove_degenerate_triangles()
    mesh = mesh.remove_duplicated_triangles()
    mesh = mesh.remove_duplicated_vertices()
    
    # Transfer colors from point cloud to mesh
    mesh.vertex_colors = pcd.colors
    
    mesh.compute_vertex_normals()
    mesh = mesh.filter_smooth_taubin(number_of_iterations=5)
    
    print(f"[INFO] BPA mesh generated with {len(mesh.triangles)} triangles in {time.time() - start_time:.2f}s")
    return mesh

# ========================
# MESH SAVING
# ========================
def save_mesh(mesh: o3d.geometry.TriangleMesh):
    print("\n[4/4] Saving enhanced mesh...")
    os.makedirs(os.path.dirname(Config.MESH_OUTPUT_FILE), exist_ok=True)
    o3d.io.write_triangle_mesh(
        Config.MESH_OUTPUT_FILE,
        mesh,
        write_vertex_colors=True,
        write_vertex_normals=True,
        compressed=True
    )
    print(f"[SUCCESS] High-quality BPA mesh saved to {Config.MESH_OUTPUT_FILE}")

# ========================
# MAIN EXECUTION
# ========================
def main():
    print("\n=== ENHANCED PUMP POINT CLOUD PROCESSOR ===")
    print(f"Using {multiprocessing.cpu_count()} CPU cores")
    Config.validate_paths()
    
    try:
        pcd = load_e57(Config.INPUT_FILE)
        if pcd is None:
            print("Failed to load point cloud data")
            return

        visualize_point_cloud(pcd)
        mesh = generate_bpa_mesh(pcd)
        save_mesh(mesh)
        
        o3d.visualization.draw_geometries(
            [mesh],
            window_name="Final Pump Mesh",
            width=1600,
            height=900,
            mesh_show_back_face=True,
            mesh_show_wireframe=False
        )
        
    except Exception as e:
        print(f"[ERROR] {str(e)}")
        raise

if __name__ == "__main__":
    main()